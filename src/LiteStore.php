<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class LiteStore extends Db
{
    /** @var Memory */
    protected $__store;

    public function __construct($attributes = [])
    {
        $class = str_replace('\\', '.', Str::lower(get_called_class()));

        $prefix = "dbs.$class";

        /** @var Builder $store */
        $this->__store = $store = (new Memory)->setNamespace($prefix);

        $db = function () use ($store) {
            $rows = $store->select('v')->where('k', 'like', $store->getNamespace() . '.row.%')->cursor();

            /** @var \Illuminate\Database\Eloquent\Model $row */
            foreach ($rows as $row) {
                yield unserialize($row->getAttribute('v'));
            }
        };

        if (!empty($attributes)) {
            $this->__model = $this->model($attributes);
        }

        parent::__construct(Core::iterator($db)->setModel($this));
    }

    /**
     * @return \PDO
     */
    protected function getPdo()
    {
        return $this->__store->getConnection()->getPdo();
    }

    /**
     * @param \Closure $callback
     * @param int $attempts
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(\Closure $callback, $attempts = 1)
    {
        return $this->__store->getConnection()->transaction($callback, $attempts);
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * @return bool
     */
    public function commit()
    {
        return $this->getPdo()->commit();
    }

    /**
     * @return bool
     */
    public function rollback()
    {
        return $this->getPdo()->rollback();
    }

    /**
     * @param Item|null $record
     * @return mixed|Item|null
     */
    public function _save(?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');
        $store = $this->__store;
        $store['row.' . $record['id']] = $record->toArray();

        $store['lc'] = time();

        return $record;
    }

    /**
     * @param Item|null $record
     * @return bool
     */
    public function _delete(?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');
        $store = $this->__store;
        $status = isset($store['row.' . $record['id']]);
        unset($store['row.' . $record['id']]);

        $store['lc'] = time();

        return $status;
    }

    public function count()
    {
        return $this->__store->select('v')
            ->where('k', 'like', $this->__store->getNamespace() . '.row.%')
            ->count();
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        return (int) $this->__store->incr('ids');
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        return (int) $this->__store['ids'];
    }

    /**
     * @return int
     */
    public function lastModified(): int
    {
        return (int) $this->__store['lc'];
    }

    /**
     * @param $id
     * @param null $default
     * @return Db|Item|null
     */
    public function find($id, $default = null)
    {
        if (is_array($id)) {
            $db = function () use ($id) {
                foreach ($id as $idRow) {
                    /** @var \Illuminate\Database\Eloquent\Model $row */
                    $row = $this->__store->find($this->__store->getNamespace() . '.row.' . $idRow);

                    if ($row) {
                        yield unserialize($row->getAttribute('v'));
                    }
                }
            };

            return $this->setEngine(Core::iterator($db)->setModel($this));
        }

        /** @var \Illuminate\Database\Eloquent\Model $row */
        if ($row = $this->__store->find($this->__store->getNamespace() . '.row.' . $id)) {
            return $this->model(unserialize($row->getAttribute('v')));
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $this->__store->where('k', 'like', $this->__store->getNamespace() . '.%')->delete();

        return 0 === $this->__store->where('k', 'like', $this->__store->getNamespace() . '.%')->count();
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
        $min = date('Y-m-d H:i:s', time() - config('modeler.cache_ttl', 24 * 3600));

        $this->__store
            ->where('k', 'like', $this->__store->getNamespace() . '.q.%')
            ->where('called_at', '<=', $min)
            ->delete()
        ;

        return 0 === $this->__store
                ->where('k', 'like', $this->__store->getNamespace() . '.q.%')
                ->where('called_at', '<=', $min)
                ->count()
            ;
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

        $isCallable = 1 === func_num_args() && is_callable($key);

        $nargs = func_num_args();

        if ($nargs === 1) {
            if (is_array($key) && false === $isCallable) {
                if (count($key) === 1) {
                    $operator   = '=';
                    $value      = array_values($key);
                    $key        = array_keys($key);
                } elseif (count($key) === 3) {
                    [$key, $operator, $value] = $key;
                }
            }
        } elseif ($nargs === 2) {
            [$value, $operator] = [$operator, '='];
        } elseif ($nargs === 3) {
            [$key, $operator, $value] = func_get_args();
        }

        $operator = Str::lower($operator);

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

        $prefix = $this->__store->getNamespace();

        $id = $prefix . '.q.' . sha1(serialize(func_get_args()) . $this->lastModified());

        $result = $this->__store[$id];

        if (!$result) {
            foreach ($this->whereNoCache($key, $operator, $value) as $row) {
                $result[] = $row;
            }

            $this->__store[$id] = serialize($result);
        } else {
            $result = unserialize($result);
        }

        $db = function () use ($result) {
            foreach ($result as $row) {
                yield $row;
            }
        };

        return $this->setEngine(Core::iterator($db)->setModel($this))->getEngine()->where($key, $operator, $value);
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     * @return Iterator
     */
    public function whereNoCache($key, $operator, $value)
    {
        $operators = [
            '=', '==', '===', 'like', 'match'
        ];

        if (!in_array($operator, $operators)) {
            $hashkey = sha1(serialize(func_get_args()));

            $ids = self::$__ids[get_called_class()][$hashkey] ?? function () use ($hashkey, $key, $operator, $value) {
                    $iterator   = $this->getEngine()->where($key, $operator, $value);
                    $ids        = [];

                    foreach ($iterator as $row) {
                        $ids[] = $row['id'];
                    }

                    unset($iterator);

                    self::$__ids[get_called_class()][$hashkey] = $ids;

                    return $ids;
                };

            return $this->withIds(value($ids));
        }

        $store = $this->__store;

        $db = function () use ($store, $key, $value) {
            $rows = $store->select('v')
                ->where('k', 'like', $store->getNamespace() . '.row.%')
                ->where('v', 'like', 'a%s:'.strlen($key).':"'.$key.'";%'.addslashes($value).'%')
                ->cursor()
            ;

            foreach ($rows as $row) {
                yield unserialize($row->getAttribute('v'));
            }
        };

        return $this->setEngine(Core::iterator($db)->setModel($this))->getEngine()->where($key, $operator, $value);
    }
}