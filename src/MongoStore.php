<?php
namespace Morbihanet\Modeler;

use Exception;
use Illuminate\Support\Str;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\ReadPreference;
use Illuminate\Database\Eloquent\Builder;

class MongoStore extends Db
{
    /** @var MongoHouse */
    protected $__store;

    public function __construct($attributes = [])
    {
        $class = str_replace('\\', '.', Str::lower(get_called_class()));

        $suffix = "ms.$class";

        /** @var Builder $store */
        $this->__store = $store = (new MongoHouse)->setNamespace($suffix);

        /** @var Modeler $modeler */
        $modeler = Core::get('modeler');

        if ('default' !== $modeler::$connection) {
            $this->__store->setConnection($modeler::$connection);
        }

        $this->__resolver = function () use ($store) {
            $rows = $this->queryCursor(
                $store->select('v')->where('k', 'like', $store->getNamespace() . '.row.%')
            );

            /** @var MongoModel $row */
            foreach ($rows as $row) {
                yield unserialize($row->getAttribute('v'));
            }
        };

        if (!empty($attributes)) {
            $this->__model = $this->model($attributes);
        }

        parent::__construct(Core::iterator($this->getResolver())->setModel($this));
    }

    protected function queryCursor(Builder $builder)
    {
        $callable = function () use ($builder) {
            foreach ($builder->get() as $row) {
                yield $row;
            }
        };

        return Core::iterator($callable);
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
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $callbackResult = $callback($this);
            } catch (Exception $e) {
                $this->rollBack();

                throw $e;
            }

            try {
                $this->commit();
            } catch (Exception $e) {
                $this->rollBack();

                throw $e;
            }

            return $callbackResult;
        }
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        try {
            Core::getMongoSession()->startTransaction([
                'readConcern' => new ReadConcern(ReadConcern::LOCAL),
                'writeConcern' => new WriteConcern(WriteConcern::MAJORITY, 1000),
                'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY),
            ]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function commit()
    {
        try {
            Core::getMongoSession()->commitTransaction();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function rollback()
    {
        try {
            Core::getMongoSession()->abortTransaction();

            return true;
        } catch (Exception $e) {
            return false;
        }
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
            $iterator   = $this->getEngine()->where($key, $operator, $value);
            $ids        = [];

            foreach ($iterator as $row) {
                $ids[] = $row['id'];
            }

            unset($iterator);

            return $this->withIds($ids);
        }

        $store = $this->__store;

        $db = function () use ($store, $key, $value) {
            $rows = $this->queryCursor($store->select('v')
                ->where('k', 'like', $store->getNamespace() . '.row.%')
                ->where('v', 'like', 'a%s:'.strlen($key).':"'.$key.'";%'.addslashes($value).'%'))
            ;

            foreach ($rows as $row) {
                yield unserialize($row->getAttribute('v'));
            }
        };

        return $this->setEngine(Core::iterator($db)->setModel($this))->getEngine()->where($key, $operator, $value);
    }
}
