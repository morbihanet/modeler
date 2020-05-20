<?php
namespace Morbihanet\Modeler;

use Closure;
use stdClass;
use Exception;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @property-read Proxy $average
 * @property-read Proxy $avg
 * @property-read Proxy $contains
 * @property-read Proxy $each
 * @property-read Proxy $every
 * @property-read Proxy $filter
 * @property-read Proxy $first
 * @property-read Proxy $flatMap
 * @property-read Proxy $groupBy
 * @property-read Proxy $keyBy
 * @property-read Proxy $map
 * @property-read Proxy $max
 * @property-read Proxy $min
 * @property-read Proxy $partition
 * @property-read Proxy $reject
 * @property-read Proxy $some
 * @property-read Proxy $sortBy
 * @property-read Proxy $sortByDesc
 * @property-read Proxy $sum
 * @property-read Proxy $unique
 */

class Iterator implements IteratorAggregate
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    /** @var mixed */
    protected $scope;

    /** @var Db|null */
    protected $model;

    /** @var array */
    protected static $queries = [];

    /** @var array */
    protected static $proxies = [
        'average', 'avg', 'contains', 'each', 'every', 'filter', 'first',
        'flatMap', 'groupBy', 'keyBy', 'map', 'max', 'min', 'partition',
        'reject', 'some', 'sortBy', 'sortByDesc', 'sum', 'unique',
    ];

    public function __construct($scope = null)
    {
        if (is_callable($scope) || $scope instanceof self) {
            $this->scope = $scope;
        } elseif (is_null($scope)) {
            $this->scope = static::empty();
        } else {
            $scope = $this->cast($scope);

            $cb = function () use ($scope) {
                foreach ($scope as $row) {
                    yield $row;
                }
            };

            $this->scope = new static($cb);
        }
    }

    protected function cast($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof Enumerable) {
            return $items->all();
        } elseif ($items instanceof Arrayable) {
            return $items->toArray();
        } elseif ($items instanceof Jsonable) {
            return json_decode($items->toJson(), true);
        } elseif ($items instanceof JsonSerializable) {
            return (array) $items->jsonSerialize();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }

    /**
     * @return array
     */
    public static function getQueries(): array
    {
        return static::$queries;
    }

    /**
     * @return Collection
     */
    public function collect()
    {
        return collect($this->toArray());
    }

    /**
     * @return Iterator
     */
    public static function empty()
    {
        return (new static([]));
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->getIterator()->valid();
    }

    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        if (is_array($this->scope)) {
            return $this->scope;
        }

        return iterator_to_array($this->getIterator());
    }

    public function toJson(int $option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $option);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * @param  string  $method
     * @return void
     */
    public static function proxify(string $method): void
    {
        static::$proxies[] = $method;
    }

    /**
     * @param  string  $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if (!in_array($key, static::$proxies)) {
            throw new Exception("Property [{$key}] does not exist on this iterator instance.");
        }

        return new Proxy($this, $key);
    }

    /**
     * @param callable|null $callback
     * @return Iterator
     */
    public function filter(?callable $callback = null)
    {
        if (is_null($callback)) {
            $callback = function ($value) {
                return (bool) $value;
            };
        }

        return $this->over(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @param string $pattern
     * @return Iterator
     */
    public function pattern(string $pattern = '*')
    {
        return $this->over(function () use ($pattern) {
            foreach ($this as $key => $value) {
                if (fnmatch($pattern, $value)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @param $from
     * @param $to
     * @return Iterator
     */
    public static function range($from, $to)
    {
        return new static(function () use ($from, $to) {
            while ($from <= $to) {
                ++$from;
                yield $from;
            }
        });
    }

    /**
     * @param callable|null $callback
     * @param null $default
     * @return Item|mixed|null
     */
    public function first(callable $callback = null, $default = null)
    {
        $iterator = $this->getIterator();

        if (is_null($callback)) {
            if (!$iterator->valid()) {
                return value($default);
            }

            $row = $iterator->current();

            if ($this->model instanceof Db) {
                return $this->model->model($this->withSelect($row));
            }

            return $row;
        }

        foreach ($iterator as $key => $value) {
            if (app()->call($callback, [$value, $key])) {
                if ($this->model instanceof Db) {
                    return $this->model->model($this->withSelect($value));
                }

                return $value;
            }
        }

        return value($default);
    }

    /**
     * @return \Generator|Item[]
     */
    public function cursor()
    {
        if (!$this->model instanceof Db) {
            return $this;
        }

        foreach ($this as $row) {
            yield $this->model->model($this->withSelect($row));
        }
    }

    /**
     * @return \Generator
     */
    public function get()
    {
        if (!$this->model instanceof Db) {
            return $this;
        }

        foreach ($this as $row) {
            yield $this->model->model($this->withSelect($row));
        }
    }

    protected function withSelect($row)
    {
        if ($this->model instanceof Db) {
            return $this->model->withSelect($row);
        }

        return $row;
    }

    /**
     * @return int
     */
    public function destroy(): int
    {
        $i = 0;

        if ($this->model instanceof Db) {
            foreach ($this as $row) {
                $this->model->model($row)->delete();
                ++$i;
            }
        }

        return $i;
    }

    /**
     * @return int
     */
    public function detach(Item $item): int
    {
        $i = 0;

        if ($this->model instanceof Db) {
            foreach ($this as $row) {
                $this->model->model($row)->detach($item);
                ++$i;
            }
        }

        return $i;
    }

    /**
     * @return int
     */
    public function attach(Item $item, array $attributes = []): int
    {
        $i = 0;

        if ($this->model instanceof Db) {
            foreach ($this as $row) {
                $this->model->model($row)->attach($item,$attributes);
                ++$i;
            }
        }

        return $i;
    }

    /**
     * @return int
     */
    public function sync(Item $item, array $attributes = []): int
    {
        $i = 0;

        if ($this->model instanceof Db) {
            foreach ($this as $row) {
                $this->model->model($row)->sync($item,$attributes);
                ++$i;
            }
        }

        return $i;
    }

    /**
     * @param array $conditions
     * @return int
     */
    public function update(array $conditions): int
    {
        $i = 0;

        if ($this->model instanceof Db) {
            foreach ($this as $row) {
                $this->model->find($row['id'])->update($conditions);
                ++$i;
            }
        }

        return $i;
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return $this->count() > 0;
    }

    /**
     * @return bool
     */
    public function notExists()
    {
        return !$this->exists();
    }

    /**
     * @param callable|null $callback
     * @param null $default
     * @return mixed|stdClass|null
     */
    public function last(?callable $callback = null, $default = null)
    {
        $array      = iterator_to_array($this->getIterator());
        $reverse    = array_reverse($array);

        $cb = function () use ($reverse) {
            foreach ($reverse as $key => $value) {
                yield $key => $value;
            }
        };

        return $this->over($cb)->first($callback, $default);
    }


    /**
     * @param callable $callback
     * @return Iterator
     */
    public function tap(callable $callback)
    {
        return $this->over(function () use ($callback) {
            foreach ($this as $key => $value) {
                app()->call($callback, [$value, $key]);

                yield $key => $value;
            }
        });
    }

    /**
     * @param $limit
     * @return Iterator
     */
    public function take($limit): self
    {
        if ($limit < 0) {
            return $this->exec('take', func_get_args());
        }

        return $this->over(function () use ($limit) {
            $iterator = $this->getIterator();

            while (--$limit) {
                if (!$iterator->valid()) {
                    break;
                }

                yield $iterator->key() => $iterator->current();

                if ($limit) {
                    $iterator->next();
                }
            }
        });
    }

    /**
     * @param $size
     * @return Iterator
     */
    public function chunk($size): self
    {
        if ($size <= 0) {
            return self::empty();
        }

        return $this->over(function () use ($size) {
            $iterator = $this->getIterator();

            while ($iterator->valid()) {
                $chunk = [];

                while (true) {
                    $chunk[$iterator->key()] = $iterator->current();

                    if (count($chunk) < $size) {
                        $iterator->next();

                        if (!$iterator->valid()) {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                yield (new self($chunk))->setModel($this->getModel());

                $iterator->next();
            }
        });
    }

    /**
     * @param int $page
     * @param int $perPage
     * @return Iterator
     */
    public function forPage(int $page, $perPage = 15)
    {
        if (1 === $page) {
            return $this->take($perPage);
        }

        return $this->slice(max(0, ($page - 1) * $perPage), $perPage);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function value(string $key, $default = null)
    {
        if ($row = $this->first()) {
            return $row->__get($key) ?? $default;
        }

        return $default;
    }

    /**
     * @param $offset
     * @param null $length
     * @return Iterator
     */
    public function slice($offset, $length = null)
    {
        if ($offset < 0 || $length < 0) {
            return $this->exec('slice', func_get_args());
        }

        $instance = $this->skip($offset);

        return is_null($length) ? $instance : $instance->take($length);
    }

    /**
     * @param $count
     * @return Iterator
     */
    public function skip($count)
    {
        return $this->over(function () use ($count) {
            $iterator = $this->getIterator();

            while ($iterator->valid() && --$count) {
                $iterator->next();
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    /**
     * @param  string|array  $value
     * @param  string|null  $key
     * @return Iterator
     */
    public function pluck($value, ?string $key = null)
    {
        return $this->over(function () use ($value, $key) {
            [$value, $key] = Core::explodePluckParameters($value, $key);

            foreach ($this as $item) {
                $itemValue = data_get($item, $value);

                if (is_null($key)) {
                    yield $itemValue;
                } else {
                    $itemKey = data_get($item, $key);

                    if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                        $itemKey = (string) $itemKey;
                    }

                    yield $itemKey => $itemValue;
                }
            }
        });
    }

    /**
     * @param callable $callback
     * @return Iterator
     */
    public function map(callable $callback)
    {
        return $this->over(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * @param Closure $callback
     * @return Iterator
     */
    public function over(Closure $callback): self
    {
        return (new self($callback->bindTo($this)))->setModel($this->getModel());
    }

    /**
     * @param $value
     * @param string $key
     * @return mixed|null
     */
    public function find($value, string $key = 'id')
    {
        return $this->where($key, $value)->first();
    }

    /**
     * @param $key
     * @param $value
     * @return Iterator
     */
    public function findBy($key, $value = null)
    {
        if (is_array($key) && null === $value) {
            $instance = $this;

            foreach ($key as $k => $v) {
                $instance = $instance->where($k, $v);
            }

            return $instance;
        }

        if (is_array($value)) {
            return $this->in($key, $value);
        }

        return $this->where($key, $value);
    }

    /**
     * @param $criteria
     * @param null $order
     * @return Iterator|null
     */
    public function findOneBy($key, $value = null, $order = null)
    {
        $result = $this->findBy($key, $value);

        return null !== $order ? $result->sortBy($order)->first() : $result->first();
    }

    /**
     * @param string $key
     * @param $value
     * @return mixed|null
     */
    public function firstWhere(string $key, $value)
    {
        return $this->where($key, $value)->first();
    }

    /**
     * @param string $key
     * @param $value
     * @return mixed|null
     */
    public function lastWhere(string $key, $value)
    {
        return $this->where($key, $value)->last();
    }

    /**
     * @param $field
     * @param $value
     * @return mixed|Item|null
     */
    public function firstBy($field, $value)
    {
        return $this->findBy($field, $value)->first();
    }

    /**
     * @param $field
     * @param $value
     * @return mixed|stdClass|null
     */
    public function lastBy($field, $value)
    {
        return $this->findBy($field, $value)->last();
    }

    /**
     * @return Iterator
     */
    public function orWhere()
    {
        $wheres = func_get_args();
        $results = collect(
            array_merge($this->toArray(), Core::store()->where(...$wheres)->toArray())
        )->unique('id')->toArray();

        return $this->over(function () use ($results) {
            foreach ($results as $result) {
                yield $result;
            }
        });
    }

    public function latest(string $column = 'created_at')
    {
        return $this->sortBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at')
    {
        return $this->sortBy($column);
    }

    public function sortByDesc($column): self
    {
        return $this->sortBy($column, 'DESC');
    }

    public function sortBy($column, string $sort = 'ASC'): self
    {
        $descending = strtoupper($sort) === 'DESC';

        $sorting = SORT_REGULAR;

        if (is_string($column) && (fnmatch('*_at', $column) || fnmatch('*id', $column))) {
            $sorting = SORT_NUMERIC;
        }

        return $this->exec('sortBy', [$column, $sorting, $descending]);
    }

    /**
     * @param $key
     * @param string|null $operator
     * @param null $value
     * @return Iterator
     */
    public function where($key, $operator = null, $value = null): self
    {
        $isCallable = false;
        $nargs = func_num_args();

        if ($nargs === 1) {
            if (is_callable($key)) {
                $isCallable = true;
            }

            if (is_array($key) && false === $isCallable) {
                [$key, $operator, $value] = $key;
                $operator = Str::lower($operator);
            }
        } elseif ($nargs === 2) {
            [$value, $operator] = [$operator, '='];
        } elseif ($nargs === 3 && null === $value) {
            [$value, $operator] = [$operator, '='];
        }

        self::$queries[] = [$key, $operator, $value];

        if (true === $isCallable) {
            return $this->filter(function($item) use ($key) {
                if ($key instanceof \Closure) {
                    return $key($item);
                } elseif (is_array($key)) {
                    return app()->call($key, [$item]);
                } else {
                    return app()->call([$key, '__invoke'], [$item]);
                }
            });
        }

        return $this->filter(function($item) use ($key, $operator, $value) {
            $item = (object) $item;
            $actual = $item->{$key} ?? null;

            $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

            if ((!is_array($actual) || !is_object($actual) || !is_numeric($actual)) && $insensitive) {
                $actual = Str::lower(Core::unaccent($actual));
            }

            if ((!is_array($value) || !is_object($value) || !is_numeric($actual)) && $insensitive) {
                $value  = Str::lower(Core::unaccent($value));
            }

            if ($insensitive) {
                $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
            }

            return Core::compare($actual, $operator, $value);
        });
    }

    /**
     * @param $conditions
     * @return $this
     */
    public function search($conditions): self
    {
        $conditions = Core::arrayable($conditions) ? $conditions->toArray() : $conditions;

        foreach ($conditions as $field => $value) {
            $this->where($field, $value);
        }

        return $this;
    }

    /**
     * @param Item $item
     * @return bool
     */
    public function contains(Item $item)
    {
        $db = Core::getDb($item);
        $fk = $db->getConcern(get_class($item)) . '_id';

        foreach ($this as $row) {
            $value = (int) $row[$fk] ?? 0;

            if ($value === (int) $item->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Item $item
     * @return bool
     */
    public function notContains(Item $item)
    {
        return !$this->contains($item);
    }

    /**
     * @param string $field
     * @param $value
     * @return $this|Iterator
     */
    public function likeI(string $field, $value)
    {
        return $this->where($field, 'like i', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orLikeI(string $field, $value)
    {
        return $this->orWhere($field, 'like i', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return $this|Iterator
     */
    public function notLikeI(string $field, $value)
    {
        return $this->where($field, 'not like i', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orNotLikeI(string $field, $value)
    {
        return $this->orWhere($field, 'not like i', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function like(string $field, $value): self
    {
        return $this->where($field, 'like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereLike(string $field, $value): self
    {
        return $this->where($field, 'like', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return Iterator
     */
    public function orLike(string $field, $value): self
    {
        return $this->orWhere($field, 'like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function notLike(string $field, $value): self
    {
        return $this->where($field, 'not like', $value);
    }


    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereNotLike(string $field, $value): self
    {
        return $this->where($field, 'not like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orNotLike(string $field, $value): self
    {
        return $this->orWhere($field, 'not like', $value);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Iterator
     */
    public function in(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Iterator
     */
    public function whereIn(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Iterator
     */
    public function orIn(string $field, array $values): self
    {
        return $this->orWhere($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Iterator
     */
    public function notIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Iterator
     */
    public function whereNotIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Iterator
     */
    public function orNotIn(string $field, array $values): self
    {
        return $this->orWhere($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param int $min
     * @param int $max
     * @return Iterator
     */
    public function between(string $field, int $min, int $max): self
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param int $min
     * @param int $max
     * @return Iterator
     */
    public function whereBetween(string $field, int $min, int $max): self
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param int $min
     * @param int $max
     * @return Iterator
     */
    public function orBetween(string $field, int $min, int $max): self
    {
        return $this->orWhere($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param int $min
     * @param int $max
     * @return Iterator
     */
    public function notBetween(string $field, int $min, int $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param int $min
     * @param int $max
     * @return Iterator
     */
    public function whereNotBetween(string $field, int $min, int $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param int $min
     * @param int $max
     * @return Iterator
     */
    public function orNotBetween(string $field, int $min, int $max): self
    {
        return $this->orWhere($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function isNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function whereNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function whereIsNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function orIsNull(string $field): self
    {
        return $this->orWhere($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function isNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function whereNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function whereIsNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @return Iterator
     */
    public function orIsNotNull(string $field): self
    {
        return $this->orWhere($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function startsWith(string $field, $value): self
    {
        return $this->where($field, 'like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function notStartsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereStartsWith(string $field, $value): self
    {
        return $this->where($field, 'like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereNotStartsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orStartsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orNotStartsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'not like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function endsWith(string $field, $value): self
    {
        return $this->where($field, 'like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function notEndsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereEndsWith(string $field, $value): self
    {
        return $this->where($field, 'like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereNotEndsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orEndsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orNotEndsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'not like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function lt(string $field, $value): self
    {
        return $this->where($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereLt(string $field, $value): self
    {
        return $this->where($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orLt(string $field, $value): self
    {
        return $this->orWhere($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function gt(string $field, $value): self
    {
        return $this->where($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereGt(string $field, $value): self
    {
        return $this->where($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function orGt(string $field, $value): self
    {
        return $this->orWhere($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function lte(string $field, $value): self
    {
        return $this->where($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Iterator
     */
    public function whereLte(string $field, $value): self
    {
        return $this->where($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return $this
     */
    public function orLte(string $field, $value): self
    {
        return $this->orWhere($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return $this
     */
    public function gte(string $field, $value): self
    {
        return $this->where($field, '>=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return $this
     */
    public function whereGte(string $field, $value): self
    {
        return $this->where($field, '>=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return $this
     */
    public function orGte(string $field, $value): self
    {
        return $this->orWhere($field, '>=', $value);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return $this
     */
    public function before($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return $this
     */
    public function whereBefore($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return $this
     */
    public function orBefore($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->orLt('created_at', $date) : $this->orLte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return $this
     */
    public function after($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
    }


    /**
     * @param $date
     * @param bool $strict
     * @return $this
     */
    public function whereAfter($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return $this
     */
    public function orAfter($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->orGt('created_at', $date) : $this->orGte('created_at', $date);
    }

    /**
     * @param string $field
     * @param $op
     * @param $date
     * @return $this
     */
    public function when(string $field, $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->where($field, $op, $date);
    }

    /**
     * @param string $field
     * @param $op
     * @param $date
     * @return $this
     */
    public function WhereWhen(string $field, $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->where($field, $op, $date);
    }

    /**
     * @param string $field
     * @param $op
     * @param $date
     * @return $this
     */
    public function orWhen(string $field, $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->orWhere($field, $op, $date);
    }

    /**
     * @return $this
     */
    public function deleted(): self
    {
        return $this->lte('deleted_at', microtime(true));
    }

    /**
     * @return $this
     */
    public function isDeleted(): self
    {
        return $this->lte('deleted_at', microtime(true));
    }

    /**
     * @return $this
     */
    public function orDeleted(): self
    {
        return $this->orLte('deleted_at', microtime(true));
    }

    /**
     * @return int
     */
    public function count(): int
    {
        if (is_array($this->scope)) {
            return count($this->scope);
        }

        $count = 0;

        foreach ($this as $row) {
            ++$count;
        }

        return $count;
    }

    /**
     * @return Traversable
     */
    public function getIterator()
    {
        $scope = $this->scope;

        if ($scope instanceof IteratorAggregate) {
            return $scope->getIterator();
        }

        if (is_array($scope)) {
            return new ArrayIterator($scope);
        }

        return app()->call($scope);
    }

    /**
     * @return Iterator
     */
    public function getValues()
    {
        return $this->over(function () {
            foreach ($this as $item) {
                yield $this->withSelect($item);
            }
        });
    }

    public function insert($values)
    {
        $model = $this->getModel();

        if ($model instanceof Db) {
            $item = $model->create($values);

            return $item->exists();
        }

        return false;
    }

    public function updateOrCreate(array $attributes, array $values = [])
    {
        return tap($this->firstOrNew($attributes), function (Item $instance) use ($values) {
            $instance->fill($values)->save();
        });
    }

    public function firstOrNew(array $attributes = [], array $values = []): Item
    {
        foreach ($attributes as $field => $value) {
            $this->where($field, $value);
        }

        if ($row = $this->first()) {
            return $row;
        }

        $db = $this->getModel();

        return $db->model($attributes + $values);
    }

    public function firstOr(Closure $callback = null)
    {
        if (!is_null($model = $this->first())) {
            return $model;
        }

        return value($callback);
    }

    public function findMany($ids)
    {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this;
        }

        return $this->in('id', $ids);
    }

    public function findOrFail($id)
    {
        $result = $this->find($id);

        $id = $id instanceof Arrayable ? $id->toArray() : $id;

        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (! is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(
            get_class($this->model->model()), $id
        );
    }

    public function findOrNew($id)
    {
        if (!is_null($model = $this->find($id))) {
            return $model;
        }

        $db = $this->getModel();

        return $db->model();
    }

    /**
     * @param array $attributes
     * @param array $values
     * @return bool
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        if (!$this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return $this->take(1)->update($values) > 0;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return Iterator|mixed
     */
    public function __call(string $name, array $arguments)
    {
        $model = $this->getModel();
        $modelActions = ['save', 'delete'];

        if (in_array($name, $modelActions) && $model instanceof Db) {
            $method = '_' . $name;

            return $model->{$method}(...$arguments);
        }

        if (self::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        if (fnmatch('where*', $name) && strlen($name) > 5) {
            if (fnmatch('where_*', $uncamelized = Core::uncamelize($name))) {
                $name = 'where';
                $arguments = array_merge([str_replace('where_', '', $uncamelized)], $arguments);
            }
        }

        return $this->exec($name, $arguments);
    }

    /**
     * Get the average value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function avg($callback = null)
    {
        return $this->collect()->avg($callback);
    }

    /**
     * @param  string|array|null  $key
     * @return mixed
     */
    public function median($key = null)
    {
        return $this->collect()->median($key);
    }

    /**
     * @param  string|array|null  $key
     * @return array|null
     */
    public function mode($key = null)
    {
        return $this->collect()->mode($key);
    }

    public function collapse()
    {
        return new static(function () {
            foreach ($this as $values) {
                if (is_array($values) || $values instanceof Enumerable) {
                    foreach ($values as $value) {
                        yield $value;
                    }
                }
            }
        });
    }

    /**
     * @param $method
     * @param array $params
     * @return Iterator
     */
    public function exec($method, array $params)
    {
        $results = $this->collect()->$method(...$params);

        if ($results instanceof Collection || $results instanceof LazyCollection || $results instanceof self) {
            return $this->over(function () use ($results) {
                foreach ($results as $k => $result) {
                    yield $k => $result;
                }
            });
        }

        return $results;
    }

    /**
     * @return Db|null
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param Db|null $model
     * @return Iterator
     */
    public function setModel(?Db $model)
    {
        $this->model = $model;

        return $this;
    }

    public function morphedByMany(string $morphClass, string $morphName): array
    {
        $rows = $this->where($morphName, $morphClass)->cursor();

        $results = [];

        foreach ($rows as $row) {
            $results[] = $morphClass::find($row[$morphName . '_id']);
        }

        return $results;
    }

    public function morphed(string $morphClass, string $morphName): ?Item
    {
        $row = $this->where($morphName, $morphClass)->first();

        if ($row) {
            return $morphClass::find($row[$morphName . '_id']);
        }

        return null;
    }
}
