<?php
namespace Morbihanet\Modeler;

use Closure;
use stdClass;
use Exception;
use Countable;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use Pagerfanta\Pagerfanta;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Pagination\LengthAwarePaginator;
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

class Iterator implements IteratorAggregate, Countable
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    /** @var mixed */
    protected $scope;

    protected ?Db $model = null;

    protected static array $queries = [];

    protected static array $proxies = [
        'average', 'avg', 'contains', 'each', 'every', 'filter', 'first',
        'flatMap', 'groupBy', 'keyBy', 'map', 'max', 'min', 'partition',
        'reject', 'some', 'sortBy', 'sortByDesc', 'sum', 'unique',
    ];

    public function __construct($scope = [])
    {
        if (is_callable($scope)) {
            $this->scope = $scope;
        } else {
            $scope = $this->cast($scope);

            $this->scope = function () use ($scope) {
                foreach ($scope as $key => $row) {
                    yield $key => $row;
                }
            };
        }
    }

    public function append($item): self
    {
        return $this->add($item);
    }

    public function prepend($item): self
    {
        $iterator = clone $this;

        return $this->over(function () use ($item, $iterator) {
            $key = -1;

            if ($item instanceof static) {
                foreach ($item as $key => $value) {
                    yield $key => $value;
                }
            } else {
                ++$key;
                yield $key => $item;
            }

            foreach ($iterator as $key => $value) {
                yield $key => $value;
            }
        });
    }

    public function add($item): self
    {
        $iterator = clone $this;

        return $this->over(function () use ($item, $iterator) {
            $key = -1;

            foreach ($iterator as $key => $value) {
                yield $key => $value;
            }

            if ($item instanceof static) {
                foreach ($item as $key => $value) {
                    yield $key => $value;
                }
            } else {
                ++$key;
                yield $key => $item;
            }
        });
    }

    public function remove($id, string $key = 'id'): self
    {
        return $this->filter(function ($item) use ($id, $key) {
            if ($id instanceof Item && $item instanceof Item) {
                return $id->toArray() !== $item->toArray();
            } else {
                if (isset($item[$key])) {
                    return $item[$key] !== $id;
                }
            }

            return false;
        });
    }

    public function __invoke()
    {
        return $this->scope;
    }

    protected function cast($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof Enumerable) {
            return $items->all();
        } elseif (Core::arrayable($items)) {
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

    public static function getQueries(): array
    {
        return static::$queries;
    }

    public function collect(): Collection
    {
        return collect($this->toArray());
    }

    public static function empty(): self
    {
        return (new static([]));
    }

    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    public function all(): array
    {
        return $this->toArray();
    }

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

    public static function proxify(string $method): void
    {
        static::$proxies[] = $method;
    }

    public function __get($key)
    {
        if (!in_array($key, static::$proxies)) {
            throw new Exception("Property [{$key}] does not exist on this iterator instance.");
        }

        return new Proxy($this, $key);
    }

    public function filter(?callable $callback = null): self
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

    public function pattern(string $pattern = '*'): self
    {
        return $this->over(function () use ($pattern) {
            foreach ($this as $key => $value) {
                if (fnmatch($pattern, $value)) {
                    yield $key => $value;
                }
            }
        });
    }

    public static function range($from, $to): self
    {
        return new static(function () use ($from, $to) {
            while ($from <= $to) {
                ++$from;
                yield $from;
            }
        });
    }

    public function deleteFirst(): bool
    {
        if ($this->model instanceof Db) {
            if ($row = $this->first()) {
                $row->delete();

                return true;
            }
        }

        return false;
    }

    /**
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
                if (!$row instanceof Item) {
                    return $this->model->model($this->withSelect($row));
                }

                return $row;
            }

            return $row;
        }

        foreach ($iterator as $key => $value) {
            if (app()->call($callback, [$value, $key])) {
                if ($this->model instanceof Db) {
                    if (!$value instanceof Item) {
                        return $this->model->model($this->withSelect($value));
                    }
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
            if (!$row instanceof Item) {
                yield $this->model->model($this->withSelect($row));
            } else {
                yield $row;
            }
        }
    }

    public function fetch()
    {
        return $this->cursor();
    }

    /**
     * @return mixed|Item|null
     */
    public function fetchOne()
    {
        return $this->first();
    }

    public function get()
    {
        if (!$this->model instanceof Db) {
            return $this;
        }

        foreach ($this as $row) {
            if (!$row instanceof Item) {
                yield $this->model->model($this->withSelect($row));
            } else {
                yield $row;
            }
        }
    }

    protected function withSelect($row)
    {
        if ($this->model instanceof Db) {
            return $this->model->withSelect($row);
        }

        return $row;
    }

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

    public function sync(Item $item, array $attributes = []): int
    {
        $i = 0;

        if ($this->model instanceof Db) {
            foreach ($this as $row) {
                $this->model->model($row)->sync($item, $attributes);
                ++$i;
            }
        }

        return $i;
    }

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

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function notExists(): bool
    {
        return !$this->exists();
    }

    /**
     * @return mixed|Item|null
     */
    public function last(?callable $callback = null, $default = null)
    {
        $array      = iterator_to_array($this->getIterator());
        $reverse    = array_reverse($array);

        return $this->over(function () use ($reverse) {
            foreach ($reverse as $key => $value) {
                yield $key => $value;
            }
        })->first($callback, $default);
    }

    public function tap(callable $callback): self
    {
        return $this->over(function () use ($callback) {
            foreach ($this as $key => $value) {
                app()->call($callback, [$value, $key]);

                yield $key => $value;
            }
        });
    }

    public function take(int $limit): self
    {
        if ($limit < 0) {
            return $this->exec('take', func_get_args());
        }

        return $this->over(function () use ($limit) {
            $iterator = $this->getIterator();

            for ($i = 0; $i < $limit; ++$i) {
                if (!$iterator->valid()) {
                    break;
                }

                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    public function chunk(int $size): self
    {
        if ($size <= 0) {
            return static::empty();
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

    public function forPage(int $page = 1, int $perPage = 15): self
    {
        if (1 === $page) {
            return $this->take($perPage);
        }

        return $this->slice(max(0, ($page - 1) * $perPage), $perPage);
    }

    public function paginator(?int $page = null, ?int $perPage = null): LengthAwarePaginator
    {
        $page = $page ?? request()->get('page', 1);
        $perPage = $perPage ?? request()->get('max_per_page', 25);

        $sliced = $this->slice(($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $sliced,
            $this->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    public function fanta(?int $page = null, ?int $perPage = null): Pagerfanta
    {
        return (new Pagerfanta(new PagerFantaAdapter($this)))
            ->setMaxPerPage($perPage ?? request()->get('max_per_page', 25))
            ->setCurrentPage($page ?? request()->get('page', 1))
        ;
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

    public function slice($offset, $length = null): self
    {
        if ($offset < 0 || $length < 0) {
            return $this->exec('slice', func_get_args());
        }

        $instance = $this;

        if (0 < $offset) {;
            $instance = $this->skip($offset);
        }

        return is_null($length) ? $instance : $instance->take($length);
    }

    public function skip(int $count): self
    {
        return $this->over(function () use ($count) {
            $iterator = $this->getIterator();

            while ($iterator->valid() && 0 < $count) {
                $iterator->next();
                --$count;
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    public function pluck($value, ?string $key = null): self
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

    public function map(callable $callback): self
    {
        return $this->over(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    public function intersect($items)
    {
        return $this->exec('intersect', func_get_args());
    }

    public function pad($size, $value)
    {
        if ($size < 0) {
            return $this->exec('pad', func_get_args());
        }

        return $this->over(function () use ($size, $value) {
            $yielded = 0;

            foreach ($this as $index => $item) {
                yield $index => $item;

                ++$yielded;
            }

            while (++$yielded < $size) {
                yield $value;
            }
        });
    }

    public function over(Closure $callback): self
    {
        return (new static($callback->bindTo($this)))->setModel($this->getModel());
    }

    /**
     * @param $value
     * @param string $key
     * @return Item|mixed|null
     */
    public function find($value, string $key = 'id')
    {
        return $this->where($key, $value)->first();
    }

    /**
     * @param string|array $key
     * @param mixed|null $value
     * @return Item|null|mixed
     */
    public function findBy($key, $value = null): self
    {
        if (is_array($key) && null === $value) {
            $instance = clone $this;

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
     * @param string|array $key
     * @param mixed|null $value
     * @param null|string $order
     * @return Item|null|mixed
     */
    public function findOneBy($key, $value = null, ?string $order = null)
    {
        $result = $this->findBy($key, $value);

        return null !== $order ? $result->sortBy($order)->first() : $result->first();
    }

    /**
     * @param string $key
     * @param $value
     * @return Item|null|mixed
     */
    public function firstWhere(string $key, $value)
    {
        return $this->where($key, $value)->first();
    }

    /**
     * @param string $key
     * @param $value
     * @return Item|null|mixed
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
        return $this->findOneBy($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return mixed|stdClass|Item|null
     */
    public function lastBy($field, $value)
    {
        return $this->findBy($field, $value)->last();
    }

    public function newQuery(): self
    {
        return Core::iterator($this->getModel()->getResolver())->setModel($this->getModel());
    }

    public function orWhere(): self
    {
        $wheres = func_get_args();
        $results = collect(
            array_merge($this->toArray(), $this->newQuery()->where(...$wheres)->toArray())
        )->unique('id')->toArray();

        return $this->over(function () use ($results) {
            foreach ($results as $result) {
                yield $result;
            }
        });
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->sortByDesc('id')->sortByDesc($column);
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->sortBy('id')->sortBy($column);
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
                if ($key instanceof Closure) {
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

    public function search($conditions): self
    {
        $conditions = Core::arrayable($conditions) ? $conditions->toArray() : $conditions;

        foreach ($conditions as $field => $value) {
            $this->where($field, $value);
        }

        return $this;
    }

    public function contains(Item $item): bool
    {
        $fk = Core::getDb($item)->getConcern(get_class($item)) . '_id';

        foreach ($this as $row) {
            $value = (int) $row[$fk] ?? 0;

            if ($value === (int) $item->id) {
                return true;
            }
        }

        return false;
    }

    public function notContains(Item $item): bool
    {
        return !$this->contains($item);
    }

    public function likeI(string $field, $value): self
    {
        return $this->where($field, 'like i', $value);
    }

    public function orLikeI(string $field, $value): self
    {
        return $this->orWhere($field, 'like i', $value);
    }

    public function notLikeI(string $field, $value): self
    {
        return $this->where($field, 'not like i', $value);
    }

    public function orNotLikeI(string $field, $value): self
    {
        return $this->orWhere($field, 'not like i', $value);
    }

    public function like(string $field, $value): self
    {
        return $this->where($field, 'like', $value);
    }

    public function whereLike(string $field, $value): self
    {
        return $this->where($field, 'like', $value);
    }

    public function orLike(string $field, $value): self
    {
        return $this->orWhere($field, 'like', $value);
    }

    public function notLike(string $field, $value): self
    {
        return $this->where($field, 'not like', $value);
    }

    public function whereNotLike(string $field, $value): self
    {
        return $this->where($field, 'not like', $value);
    }

    public function orNotLike(string $field, $value): self
    {
        return $this->orWhere($field, 'not like', $value);
    }

    public function in(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    public function whereIn(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    public function orIn(string $field, array $values): self
    {
        return $this->orWhere($field, 'in', $values);
    }

    public function notIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    public function whereNotIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    public function orNotIn(string $field, array $values): self
    {
        return $this->orWhere($field, 'not in', $values);
    }

    public function between(string $field, int $min, int $max): self
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    public function whereBetween(string $field, int $min, int $max): self
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    public function orBetween(string $field, int $min, int $max): self
    {
        return $this->orWhere($field, 'between', [$min, $max]);
    }

    public function notBetween(string $field, int $min, int $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    public function whereNotBetween(string $field, int $min, int $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    public function orNotBetween(string $field, int $min, int $max): self
    {
        return $this->orWhere($field, 'not between', [$min, $max]);
    }

    public function isNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    public function whereNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    public function whereIsNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    public function orIsNull(string $field): self
    {
        return $this->orWhere($field, 'is', 'null');
    }

    public function isNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    public function whereNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    public function whereIsNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    public function orIsNotNull(string $field): self
    {
        return $this->orWhere($field, 'is not', 'null');
    }

    public function startsWith(string $field, $value): self
    {
        return $this->where($field, 'like', $value . '%');
    }

    public function notStartsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', $value . '%');
    }

    public function whereStartsWith(string $field, $value): self
    {
        return $this->where($field, 'like', $value . '%');
    }

    public function whereNotStartsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', $value . '%');
    }

    public function orStartsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'like', $value . '%');
    }

    public function orNotStartsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'not like', $value . '%');
    }

    public function endsWith(string $field, $value): self
    {
        return $this->where($field, 'like', '%' . $value);
    }

    public function notEndsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', '%' . $value);
    }

    public function whereEndsWith(string $field, $value): self
    {
        return $this->where($field, 'like', '%' . $value);
    }

    public function whereNotEndsWith(string $field, $value): self
    {
        return $this->where($field, 'not like', '%' . $value);
    }

    public function orEndsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'like', '%' . $value);
    }

    public function orNotEndsWith(string $field, $value): self
    {
        return $this->orWhere($field, 'not like', '%' . $value);
    }

    public function lt(string $field, $value): self
    {
        return $this->where($field, '<', $value);
    }

    public function whereLt(string $field, $value): self
    {
        return $this->where($field, '<', $value);
    }

    public function orLt(string $field, $value): self
    {
        return $this->orWhere($field, '<', $value);
    }

    public function gt(string $field, $value): self
    {
        return $this->where($field, '>', $value);
    }

    public function whereGt(string $field, $value): self
    {
        return $this->where($field, '>', $value);
    }

    public function orGt(string $field, $value): self
    {
        return $this->orWhere($field, '>', $value);
    }

    public function lte(string $field, $value): self
    {
        return $this->where($field, '<=', $value);
    }

    public function whereLte(string $field, $value): self
    {
        return $this->where($field, '<=', $value);
    }

    public function orLte(string $field, $value): self
    {
        return $this->orWhere($field, '<=', $value);
    }

    public function gte(string $field, $value): self
    {
        return $this->where($field, '>=', $value);
    }

    public function whereGte(string $field, $value): self
    {
        return $this->where($field, '>=', $value);
    }

    public function orGte(string $field, $value): self
    {
        return $this->orWhere($field, '>=', $value);
    }

    public function before($date, string $field = 'created_at', bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->lt($field, $date) : $this->lte($field, $date);
    }

    public function whereBefore($date, string $field = 'created_at', bool $strict = true): self
    {
        return $this->before($date, $field, $strict);
    }

    public function orBefore($date, string $field = 'created_at', bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->orLt($field, $date) : $this->orLte($field, $date);
    }

    public function after($date, string $field = 'created_at', bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->gt($field, $date) : $this->gte($field, $date);
    }

    public function whereAfter($date, string $field = 'created_at', bool $strict = true): self
    {
        return $this->after($date, $field, $strict);
    }

    public function orAfter($date, string $field = 'created_at', bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->orGt($field, $date) : $this->orGte($field, $date);
    }

    public function when(string $field, $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->where($field, $op, $date);
    }

    public function whereWhen(string $field, $op, $date): self
    {
        return $this->when($field, $op, $date);
    }

    public function orWhen(string $field, $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->orWhere($field, $op, $date);
    }

    public function deleted(): self
    {
        return $this->lte('deleted_at', microtime(true));
    }

    public function isDeleted(): self
    {
        return $this->lte('deleted_at', microtime(true));
    }

    public function orDeleted(): self
    {
        return $this->orLte('deleted_at', microtime(true));
    }

    public function count(): int
    {
        return count(array_keys($this->toArray()));
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

    public function getValues(): self
    {
        return $this->over(function () {
            foreach ($this as $item) {
                yield $this->withSelect($item);
            }
        });
    }

    public function flip(): self
    {
        return $this->over(function () {
            foreach ($this as $key => $value) {
                yield $value => $key;
            }
        });
    }

    public function flatten($depth = INF)
    {
        $instance = $this->over(function () use ($depth) {
            foreach ($this as $item) {
                if (!is_array($item) && !$item instanceof Enumerable) {
                    yield $item;
                } elseif ($depth === 1) {
                    yield from $item;
                } else {
                    yield from $this->over($item)->flatten($depth - 1);
                }
            }
        });

        return $instance->values();
    }

    public function cacheForever(Closure $callable): self
    {
        return $this->cacheFor($callable, '10 YEAR');
    }

    public function cacheFor(Closure $callable, $time = '2 HOUR'): self
    {
        $class = $this->model instanceof Db ? get_class($this->model) : get_called_class();

        $rows = Core::cache($class)->setFor(Core::getClosureId($callable), function () use ($callable) {
            $rows = [];

            foreach ($callable($this) as $row) {
                $rows[] = $row;
            }

            return $rows;
        }, $time);

        return $this->over(function () use ($rows) {
            foreach ($rows as $row) {
                if ($this->model instanceof Db) {
                    yield $this->model->create($row);
                } else {
                    yield $row;
                }
            }
        });
    }

    public function call(callable $callable): self
    {
        return $this->customize($callable);
    }

    public function customize(callable $callable): self
    {
        $model = $this->getModel();

        if ($model instanceof Db) {
            return $this->over(function () use ($callable, $model) {
                foreach ($this as $row) {
                    $item = $row instanceof Item ? $row : $model->create($row);

                    $value = $callable($item);

                    if ($value) {
                        yield $value;
                    }
                }
            });
        }

        return $this;
    }

    public function insert(array $values): bool
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

    public function firstOr(?Closure $callback = null)
    {
        if (!is_null($model = $this->first())) {
            return $model;
        }

        return value($callback);
    }

    public function findMany($ids): self
    {
        $ids = Core::arrayable($ids) ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this;
        }

        return $this->in('id', $ids);
    }

    public function findOrFail($id)
    {
        $result = $this->find($id);

        $id = Core::arrayable($id) ? $id->toArray() : $id;

        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(
            get_class($this->model->model()), $id
        );
    }

    public function findOrNew($id): Item
    {
        if (!is_null($model = $this->find($id))) {
            return $model;
        }

        $db = $this->getModel();

        return $db->model();
    }

    public function updateOrInsert(array $attributes, array $values = []): bool
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
    public function __call($name, $arguments)
    {
        if (static::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        $model = $this->getModel();
        $modelActions = ['save', 'delete'];

        if (in_array($name, $modelActions) && $model instanceof Db) {
            $method = '_' . $name;

            return $model->{$method}(...$arguments);
        }

        $uncamelized = Core::uncamelize($name);

        if ($modeler = Core::get('modeler')) {
            if (in_array($name, get_class_methods($modeler))) {
                $args = array_merge([$this], $arguments);

                return (new $modeler)->{$name}(...$args);
            }

            $methodModeler = Str::camel('scope_' . $uncamelized);

            if (in_array($methodModeler, get_class_methods($modeler))) {
                $args = array_merge([$this], $arguments);

                return (new $modeler)->{$methodModeler}(...$args);
            }
        }

        if (fnmatch('where*', $name) && strlen($name) > 5) {
            if (fnmatch('where_*', $uncamelized)) {
                $arguments = array_merge([str_replace('where_', '', $uncamelized)], $arguments);

                return $this->where(...$arguments);
            }
        }

        if (fnmatch('has*', $name) && strlen($name) > 3) {
            if (fnmatch('has_*', $uncamelized)) {
                $arguments = array_merge([str_replace('has_', '', $uncamelized . '_id'), '>', 0], $arguments);

                return $this->where(...$arguments);
            }
        }

        if (fnmatch('doesntHave*', $name) && strlen($name) > 10) {
            if (fnmatch('doesnt_have_*', $uncamelized)) {
                $arguments = array_merge(
                    [str_replace('doesnt_have_', '', $uncamelized . '_id'), '<', 1],
                    $arguments
                );

                return $this->where(...$arguments);
            }
        }

        return $this->exec($name, $arguments);
    }

    /**
     * @param  callable|string|null  $callback
     * @return float|int|mixed
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

    public function mode($key = null): ?array
    {
        return $this->collect()->mode($key);
    }

    public function collapse(): self
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

    public function diff($items): self
    {
        return $this->exec('diff', func_get_args());
    }

    public function crossJoin(...$arrays): self
    {
        return $this->exec('crossJoin', func_get_args());
    }

    public function groupBy(string $groupBy, bool $preserveKeys = false): self
    {
        return $this->exec('groupBy', func_get_args());
    }

    public function union($items): self
    {
        return $this->exec('union', func_get_args());
    }

    public function nth(int $step, int $offset = 0): self
    {
        return $this->over(function () use ($step, $offset) {
            $position = 0;

            foreach ($this as $item) {
                if ($position % $step === $offset) {
                    yield $item;
                }

                ++$position;
            }
        });
    }

    public function exec(string $method, array $params)
    {
        $results = $this->collect()->$method(...$params);

        if ($results instanceof Collection || $results instanceof LazyCollection || $results instanceof static) {
            return $this->over(function () use ($results) {
                foreach ($results as $k => $result) {
                    yield $k => $result;
                }
            });
        }

        return $results;
    }

    public function getModel(): ?Db
    {
        return $this->model;
    }

    public function setModel(?Db $model): self
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

    /**
     * @param string $morphClass
     * @param string $morphName
     * @return Item|mixed|null
     */
    public function morphed(string $morphClass, string $morphName)
    {
        $row = $this->where($morphName, $morphClass)->first();

        if ($row) {
            return $morphClass::find($row[$morphName . '_id']);
        }

        return null;
    }
}
