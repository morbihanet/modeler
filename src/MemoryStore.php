<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;

class MemoryStore extends Db
{
    /** @var string */
    protected $__prefix;

    /** @var array */
    protected static $data = [];

    /** @var array */
    protected static $lc = [];

    /** @var array */
    protected static $ids = [];

    public function __construct(array $attributes = [])
    {
        $class = str_replace('\\', '.', Str::lower(get_called_class()));

        $prefix = $this->__prefix = "dba.$class";

        $db = function () use ($prefix) {
            $ids = array_keys(static::$data[$prefix] ?? []);

            if (!empty($ids)) {
                $cursor = (int) min($ids);
                $max = (int) max($ids);
                unset($ids);

                while ($cursor <= $max) {
                    if ($row = static::$data[$prefix][$cursor] ?? null) {
                        yield $this->unserialize($row);
                    }

                    ++$cursor;
                }
            }
        };

        if (!empty($attributes)) {
            $this->__model = $this->model($attributes);
        }

        parent::__construct(Core::iterator($db)->setModel($this));
    }

    /**
     * @return Item|null
     */
    public function first()
    {
        $ids = array_keys(static::$data[$this->__prefix] ?? []);

        if (!empty($ids)) {
            $id = (int) min($ids);
            unset($ids);

            return $this->model(
                $this->withSelect(
                    $this->unserialize(
                        static::$data[$this->__prefix][$id] ?? null
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
        $ids = array_keys(static::$data[$this->__prefix] ?? []);

        if (!empty($ids)) {
            $id = (int) max($ids);
            unset($ids);

            return $this->model(
                $this->withSelect(
                    $this->unserialize(
                        static::$data[$this->__prefix][$id] ?? null
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
        static::$data[$this->__prefix][$record['id']] = $this->serialize($record->toArray());
        static::$lc[$this->__prefix] = time();

        return $record;
    }

    public static function empty()
    {
        static::$lc = static::$ids = static::$data = [];
    }

    /**
     * @param Item $record
     * @return bool
     */
    public function _delete(Item $record)
    {
        unset(static::$data[$this->__prefix][$record['id']]);
        static::$lc[$this->__prefix] = time();

        return true;
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        $id = static::$ids[$this->__prefix] ?? 0;
        ++$id;
        static::$ids[$this->__prefix] = $id;

        return (int) $id;
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        return (int) static::$ids[$this->__prefix] ?? 1;
    }

    /**
     * @return int
     */
    public function lastModified(): int
    {
        return (int) static::$lc[$this->__prefix] ?? time();
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
                    if ($row = static::$data[$this->__prefix][$idRow] ?? null) {
                        yield $this->withSelect($this->unserialize($row));
                    }
                }
            };

            return $this->setEngine(Core::iterator($db)->setModel($this));
        }

        if ($row = static::$data[$this->__prefix][$id] ?? null) {
            return $this->model($this->withSelect($this->unserialize($row)));
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function beginTransaction(): bool
    {
        Core::set('transaction_' . $this->__prefix, [
            'data' => static::$data[$this->__prefix] ?? [],
            'lc' => static::$lc[$this->__prefix] ?? time(),
            'ids' => static::$ids[$this->__prefix] ?? [],
        ]);

        return true;
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
        return true;
    }

    /**
     * @return bool
     */
    public function rollback(): bool
    {
        $data = Core::get('transaction_' . $this->__prefix, []);

        if (!empty($data)) {
            static::$data[$this->__prefix] = $data['data'];
            static::$lc[$this->__prefix] = $data['lc'];
            static::$ids[$this->__prefix] = $data['ids'];
        }

        Core::delete('transaction_' . $this->__prefix);

        return false;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        static::$data[$this->__prefix] = [];

        return true;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count(static::$data[$this->__prefix] ?? []);
    }
}
