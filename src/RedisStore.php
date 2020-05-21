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
            $ids = Redis::hkeys($prefix);

            if (!empty($ids)) {
                $cursor = (int) min($ids);
                $max = (int) max($ids);
                unset($ids);

                while ($cursor <= $max) {
                    if ($row = Redis::hget($prefix, $cursor)) {
                        yield $this->unserialize($row);
                    }

                    ++$cursor;
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
        $ids = Redis::hkeys($this->__prefix);

        if (!empty($ids)) {
            $id = (int) min($ids);
            unset($ids);

            return $this->model(
                $this->withSelect(
                    $this->unserialize(
                        Redis::hget($this->__prefix, $id)
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
        $ids = Redis::hkeys($this->__prefix);

        if (!empty($ids)) {
            $id = (int) max($ids);
            unset($ids);

            return $this->model(
                $this->withSelect(
                    $this->unserialize(
                        Redis::hget($this->__prefix, $id)
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

        Redis::hdel($prefix, (int) $record['id']);
        Redis::set($prefix . '.lc', time());

        return true;
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        return (int) Redis::incr($this->__prefix . '.ids');
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        return (int) Redis::get($this->__prefix . '.ids');
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
