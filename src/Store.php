<?php
namespace Morbihanet\Modeler;

use PDO;
use Closure;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class Store extends Db
{
    /** @var Warehouse */
    protected $__store;

    public function __construct($attributes = [])
    {
        $class = str_replace('\\', '.', Str::lower(get_called_class()));

        $suffix = "dbs.$class";

        /** @var Builder $store */
        $this->__store = $store = (new Warehouse)->setNamespace($suffix);

        /** @var Modeler $modeler */
        $modeler = Core::get('modeler');

        if ('default' !== $modeler::$connection) {
            $this->__store->setConnection($modeler::$connection);
        }

        $this->__resolver = function () use ($store) {
            $rows = $store->select('v')->where('k', 'like', $store->getNamespace() . '.row.%')->cursor();

            /** @var \Illuminate\Database\Eloquent\Model $row */
            foreach ($rows as $row) {
                yield unserialize($row->getAttribute('v'));
            }
        };

        if (!empty($attributes)) {
            $this->__model = $this->model($attributes);
        }

        parent::__construct(Core::iterator($this->getResolver())->setModel($this));
    }

    /**
     * @return PDO
     */
    protected function getPdo()
    {
        return $this->__store->getConnection()->getPdo();
    }

    /**
     * @param Closure $callback
     * @param int $attempts
     * @return mixed
     * @throws Throwable
     */
    public function transaction(Closure $callback, int $attempts = 1)
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
        $this->fire('commiting', $this);
        $status = $this->getPdo()->commit();
        $this->fire('commited', $this);

        return $status;
    }

    /**
     * @return bool
     */
    public function rollback()
    {
        $this->fire('rollingback', $this);
        $status = $this->getPdo()->rollback();
        $this->fire('rolledback', $this);

        return $status;
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
            ->where('e', 0)
            ->where('k', 'like', $this->__store->getNamespace() . '.row.%')
            ->count();
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        $store = $this->__store;

        $id = (int) $store->incr('ids');

        if (null !== $this->find($id)) {
            return $this->makeId();
        }

        return $id;
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
            $this->__resolver = function () use ($id) {
                foreach ($id as $idRow) {
                    /** @var \Illuminate\Database\Eloquent\Model $row */
                    $row = $this->__store->find($this->__store->getNamespace() . '.row.' . $idRow);

                    if ($row) {
                        yield unserialize($row->getAttribute('v'));
                    }
                }
            };

            return $this->setEngine(Core::iterator($this->getResolver())->setModel($this));
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
        $this->__store->where('e', 0)->where('k', 'like', $this->__store->getNamespace() . '.%')->delete();

        return 0 === $this->__store->where('e', 0)->where('k', 'like', $this->__store->getNamespace() . '.%')->count();
    }

    /**
     * @return bool
     */
    public function clearCache(): bool
    {
        $min = date('Y-m-d H:i:s', time() - config('modeler.cache_ttl', 24 * 3600));

        $this->__store
            ->where('e', 0)
            ->where('k', 'like', $this->__store->getNamespace() . '.q.%')
            ->where('called_at', '<=', $min)
            ->delete()
        ;

        return 0 === $this->__store
                ->where('e', 0)
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

        $nargs = func_num_args();

        $isCallable = 1 === $nargs && is_callable($key);

        if ($nargs === 1 && !$isCallable) {
            if (is_array($key)) {
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
            $ids = [];

            foreach ($this->getEngine()->where($key, $operator, $value) as $row) {
                $ids[] = $row['id'];
            }

            return $this->withIds($ids);
        }

        if (false === $this->isCache()) {
            return $this->whereNoCache($key, $operator, $value);
        }

        $prefix = $this->__store->getNamespace();

        if (!$result = $this->__store[$id = $prefix . '.q.' . sha1(serialize(func_get_args()) . $this->lastModified())]) {
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
            $ids = [];

            foreach ($this->getEngine()->where($key, $operator, $value) as $row) {
                $ids[] = $row['id'];
            }

            return $this->withIds($ids);
        }

        $store = $this->__store;

        $db = function () use ($store, $key, $value) {
            foreach ($store
                         ->select('v')
                         ->where('e', 0)
                         ->where('k', 'like', $store->getNamespace() . '.row.%')
                         ->where('v', 'like', 'a%s:'.strlen($key).':"'.$key.'";%'.addslashes($value).'%')
                         ->cursor() as $row
            ) {
                yield unserialize($row->getAttribute('v'));
            }
        };

        return $this->setEngine(Core::iterator($db)->setModel($this))->getEngine()->where($key, $operator, $value);
    }
}
