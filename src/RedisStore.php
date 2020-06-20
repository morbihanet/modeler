<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;

class RedisStore extends Db
{
    /** @var string */
    protected $__prefix;

    public function __construct(array $attributes = [])
    {
        $class = str_replace('\\', '.', Str::lower(get_called_class()));

        $prefix = $this->__prefix = "dbf.$class";

        $this->__resolver = function () use ($prefix) {
            $lastId = $this->lastInsertId();
            $min = Redis::get($this->__prefix . '.min') ?? 0;

            if ($lastId > 0 && $min > 0) {
                for ($i = $min; $i <= $lastId; ++$i) {
                    if ($row = Redis::hget($prefix, $i)) {
                        yield $this->unserialize($row);
                    }
                }
            }
        };

        if (!empty($attributes)) {
            $this->__model = $this->model($attributes);
        }

        parent::__construct(Core::iterator($this->getResolver())->setModel($this));
    }

    /**
     * @return Item|null
     */
    public function first()
    {
        $min = Redis::get($this->__prefix . '.min') ?? 0;

        if (0 < $min) {
            return $this->model(
                $this->withSelect(
                    $this->unserialize(
                        Redis::hget($this->__prefix, $min)
                    )
                )
            );
        }

        return null;
    }

    /**
     * @return Item|null
     */
    public function last()
    {
        $lastId = $this->lastInsertId();

        if (0 < $lastId) {
            return $this->model(
                $this->withSelect(
                    $this->unserialize(
                        Redis::hget($this->__prefix, $lastId)
                    )
                )
            );
        }

        return null;
    }

    /**
     * @param Item $record
     * @return Item
     */
    public function _save(Item $record)
    {
        $prefix = $this->__prefix;

        Redis::hset($prefix, (int) $record['id'], $this->serialize($record->toArray()));
        Redis::set($prefix . '.lc', time());

        return $record;
    }

    /**
     * @param Item $record
     * @return bool
     */
    public function _delete(Item $record)
    {
        $prefix = $this->__prefix;

        $id = (int) $record['id'];

        Redis::hdel($prefix, $id);
        Redis::set($prefix . '.lc', time());

        $min = (int) Redis::get($prefix . '.min');

        if ($min >= $id) {
            $next = $id + 1;

            if (Redis::hget($prefix, $next)) {
                Redis::set($prefix . '.min', $next);
            } else {
                Redis::del($prefix . '.min');
            }
        }

        return true;
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        $id = (int) Redis::incr($this->__prefix . '.ids');

        $min = Redis::get($this->__prefix . '.min');

        if (null === $min) {
            Redis::set($this->__prefix . '.min', $id);;
        }

        return $id;
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        $id = Redis::get($this->__prefix . '.ids') ?? 0;

        return (int) $id;
    }

    /**
     * @return int
     */
    public function lastModified(): int
    {
        return (int) Redis::get($this->__prefix . '.lc');
    }

    /**
     * @param $id
     * @param null $default
     * @return Db|Item|null
     */
    public function find($id, $default = null)
    {
        if (is_array($id)) {
            $this->__resolver = function () use ($id) {
                foreach ($id as $idRow) {
                    if ($row = Redis::hget($this->__prefix, $idRow)) {
                        yield $this->withSelect($this->unserialize($row));
                    }
                }
            };

            return $this->setEngine(Core::iterator($this->getResolver())->setModel($this));
        }

        if ($row = Redis::hget($this->__prefix, $id)) {
            return $this->model($this->withSelect($this->unserialize($row)));
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function beginTransaction(): bool
    {
        /** @var \Predis\Response\Status $status */
        $status = Redis::multi();

        return $status->getPayload() === 'OK';
    }

    /**
     * @param \Closure $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(\Closure $callback)
    {
        $this->beginTransaction();

        try {
            $callbackResult = $callback($this);
        } catch (\Exception $e) {
            $this->rollBack();

            throw $e;
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        $this->commit();

        return $callbackResult;
    }

    /**
     * @return bool
     */
    public function commit(): bool
    {
        return is_array(Redis::exec());
    }

    /**
     * @return bool
     */
    public function rollback(): bool
    {
        /** @var \Predis\Response\Status $status */
        $status = Redis::discard();

        return $status->getPayload() === 'OK';
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $keys = Redis::keys($this->__prefix . '.*');

        /** @var \Predis\Pipeline\Pipeline $pipeline */
        $pipeline = Redis::pipeline();

        foreach ($keys as $key) {
            $pipeline->del($key);
        }

        $pipeline->execute();

        Redis::del($this->__prefix);

        return empty(Redis::hkeys($this->__prefix));
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
        $keys = Redis::keys($this->__prefix . '.q.*');

        /** @var \Predis\Pipeline\Pipeline $pipeline */
        $pipeline = Redis::pipeline();

        foreach ($keys as $key) {
            $pipeline->del($key);
        }

        $pipeline->execute();

        return empty(Redis::keys($this->__prefix . '.q.*'));
    }

    /**
     * @return int
     */
    public function count()
    {
        return count(Redis::hkeys($this->__prefix));
    }

    /**
     * @param $key
     * @param null $operator
     * @param null $value
     * @return Iterator
     */
    public function where($key, $operator = null, $value = null)
    {
        $this->__where = true;
        $this->__wheres[] = func_get_args();

        $isCallable = 1 === func_num_args() && is_callable($key);

        if (true === $isCallable) {
            $iterator   = $this->getEngine()->where($key, $operator, $value);
            $ids        = [];

            foreach ($iterator as $row) {
                $ids[] = $row['id'];
            }

            unset($iterator);

            return $this->withIds($ids);
        }

        if (false === $this->isCache()) {
            return $this->whereNoCache($key, $operator, $value);
        }

        $id = $this->__prefix . '.q.' . sha1(serialize(func_get_args()) . $this->lastModified());

        $ids = Redis::get($id);

        if (!$ids) {
            $ids = $this->whereNoCache($key, $operator, $value, false);
            Redis::set($id, $this->serialize($ids));
            Redis::expire($id, config('modeler.cache_ttl', 24 * 3600));
        } else {
            $ids = $this->unserialize($ids);
        }

        return $this->withIds($ids);
    }

    /**
     * @param $key
     * @param null $operator
     * @param null $value
     * @param bool $returnIterator
     * @return Iterator|mixed|null
     */
    public function whereNoCache($key, $operator = null, $value = null)
    {
        $iterator   = $this->getEngine()->where($key, $operator, $value);
        $ids        = [];

        foreach ($iterator as $row) {
            $ids[] = $row['id'];
        }

        unset($iterator);

        return $this->withIds($ids);
    }
}
