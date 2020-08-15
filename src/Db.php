<?php
namespace Morbihanet\Modeler;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Faker\Generator as Faker;
use Illuminate\Support\Traits\Macroable;

/**
 * @method Iterator sortBy(string $key, int $options = SORT_REGULAR, bool $descending = false)
 * @method Iterator sortByDesc(string $key, int $options = SORT_REGULAR)
 * @method int sum(string $key)
 * @method int min(string $key)
 * @method int max(string $key)
 * @method float avg(string $key)
 * @method \Generator|Item[] cursor()
 *
 * @see Iterator
 */

class Db
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    protected ?Iterator $engine = null;
    protected ?string $modeler = null;
    protected array $withQuery = [];
    protected int $__count = 0;
    protected bool $__cache = false;
    protected bool $__where = false;
    protected ?Item $__model = null;
    protected static array $__ids = [];
    protected static array $__booted = [];
    protected array $__select = [];
    protected array $__wheres = [];
    protected ?Closure $__resolver = null;

    public function __construct(Iterator $engine)
    {
        $this->engine = $engine;
        $this->modeler = Core::get('modeler');

        if (!isset(static::$__booted[$class = get_called_class()])) {
            static::$__booted[$class] = true;

            $this->fire('booting', $engine);
            $this->traits();
            $this->fire('booted', $engine);
        }
    }

    /**
     * @param mixed ...$fields
     * @return $this
     */
    public function select(...$fields)
    {
        $this->__select = $fields;

        return $this;
    }

    /**
     * @return array
     */
    public function getSelect()
    {
        return $this->__select;
    }

    protected function traits()
    {
        $booted = [];

        foreach (class_uses_recursive($class = get_called_class()) as $trait) {
            $method = 'boot'.class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, $booted)) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }
        }
    }

    /**
     * @return Db
     */
    public static function self(): self
    {
        return app()->make(get_called_class());
    }

    /**
     * @param array $attributes
     * @return Item
     */
    public static function make(array $attributes = []): Item
    {
        return static::self()->create($attributes);
    }

    /**
     * @param array $data
     * @return Item
     */
    public function create(array $data = []): Item
    {
        return $this->model($data)->save();
    }

    /**
     * @param array $data
     * @return Item
     */
    public function insert(array $data = []): Item
    {
        return $this->model($data)->save();
    }

    /**
     * @param array|string|null $only
     * @return Item
     */
    public function asPosted($only = null): Item
    {
        return $this->create($only ? Arr::only($_POST, func_get_args()) : $_POST);
    }

    /**
     * @return Db
     */
    public function newQuery(): self
    {
        return static::self();
    }

    /**
     * @param string $class
     * @return Db
     */
    public function observe(string $class): self
    {
        $observers = Core::get('itdb.observers', []);
        $observers[get_called_class()] = $class;
        Core::set('itdb.observers', $observers);

        return $this;
    }

    /**
     * @param array $data
     * @return Item
     */
    public function new(array $data = []): Item
    {
        return $this->model($data);
    }

    /**
     * @param Item|array $data
     * @return Item
     */
    public function model($data = []): Item
    {
        $item = !$data instanceof Item ? Core::model($this, $data) : $data;

        return $this->fire('model', $item);
    }

    /**
     * @param Item $model
     * @param callable|null $callback
     * @return Item
     */
    public function save(Item $model, ?callable $callback = null): Item
    {
        $this->fire('saving', $model);

        if (is_callable($callback)) {
            app()->call($callback, [$model, $this]);
        }

        $now = time();

        if (!$model->exists()) {
            $this->fire('creating', $model);
            $model['id'] = $this->makeId();
            $model['created_at'] = $now;
        } else {
            $this->fire('updating', $model);
        }

        $model['updated_at'] = $now;
        unset($model['__db']);
        unset($model['__original']);

        $saved = $this->engine->save($model);

        $this->fire('saved', $saved);

        return $model;
    }

    /**
     * @param Item $model
     * @param callable|null $callback
     * @return bool
     */
    public function delete(Item $model, ?callable $callback = null): bool
    {
        $this->fire('deleting', $model);

        if (is_callable($callback)) {
            app()->call($callback, [$model, $this]);
        }

        $status = $this->engine->delete($model);

        $this->fire('deleted', $model);

        return $status;
    }

    /**
     * @return int
     */
    public function makeId(): int
    {
        return Core::dyndb('itdb.' . get_called_class() . '.ids')->increment('id');
    }

    /**
     * @return int
     */
    public function lastInsertId(): int
    {
        return Core::dyndb('itdb.' . get_called_class() . '.ids')->getOr('id', 1);
    }

    /**
     * @return \Generator
     */
    public static function fetchAll()
    {
        return (new static)->getEngine()->cursor();
    }

    /**
     * @return bool
     */
    public static function clear()
    {
        $instance = new static;

        if ($instance instanceof Modeler) {
            return $instance->flush();
        }

        return false;
    }

    /**
     * @return Iterator
     */
    public function all()
    {
        return $this->engine;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->engine->toArray();
    }

    /**
     * @return \Traversable
     */
    public function iterator()
    {
        return $this->engine->getIterator();
    }

    /**
     * @param int $option
     * @return string
     */
    public function toJson(int $option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->toArray(), $option);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @param $id
     * @param null $default
     * @return Item|null
     */
    public function __invoke($id, $default = null)
    {
        return $this->find($id, $default);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $this->fire($name, $this);

        if (static::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        /** @var Modeler $modeler */
        $modeler = app($this->modeler);

        if (in_array($name, get_class_methods($modeler))) {
            $arguments[] = $this;

            return $modeler->{$name}(...$arguments);
        }

        if ($this->__model instanceof Item) {
            return $this->__model->{$name}(...$arguments);
        }

        $method = Core::uncamelize($name);

        if (fnmatch('where_*', $method)) {
            return $this->where(str_replace('where_', '', $method), '=', $arguments[0]);
        }

        return $this->engine->{$name}(...$arguments);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $self = static::self();
        $self->fire($name, $self);

        if (static::hasMacro($name)) {
            return $self->macroCall($name, $arguments);
        }

        return $self->getEngine()->{$name}(...$arguments);
    }

    /**
     * @return Iterator
     */
    public function getEngine(): Iterator
    {
        return $this->engine;
    }

    /**
     * @param Iterator $engine
     * @return Db
     */
    public function setEngine(Iterator $engine): Db
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * @param int $_count
     * @return Db
     */
    public function setCount(int $_count): Db
    {
        $this->__count = $_count;

        return $this;
    }

    /**
     * @return int
     */
    public function incrementCount(): int
    {
        ++$this->__count;

        return $this->__count;
    }

    /**
     * @return int
     */
    public function decrementCount(): int
    {
        if (0 < $this->__count) {
            --$this->__count;
        }

        return $this->__count;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->__count;
    }

    /**
     * @return bool
     */
    public function isWhere(): bool
    {
        return $this->__where;
    }

    /**
     * @param callable|null $callable
     * @return Macro
     */
    public function factory(?callable $callable = null)
    {
        $factory = Macro::__instance('it_factory_' . get_called_class());

        $factory->_times = 1;

        $factory->new('times', function (int $t = 1) use ($factory) {
            $factory->_times = $t;

            return $factory;
        })->new('make', function (array $attrs = [], bool $toCollection = false, ?Faker $faker = null) use ($factory, $callable) {
            $collection = [];

            for ($i = 0; $i < $factory->_times; ++$i) {
                $row = is_callable($callable) ? $callable($faker ?? Core::faker()) : $this->seeder($faker ?? Core::faker());
                $collection[] = $this->model(array_merge($row, $attrs));
            }

            if (true === $toCollection) {
                $cb = function () use ($collection) {
                    foreach ($collection as $row) {
                        yield $row;
                    }
                };

                return $this->setEngine(Core::iterator($cb)->setModel($this));
            }

            return $collection;
        })->new('create', function (array $attrs = [], bool $toCollection = false, ?Faker $faker = null) use ($factory, $callable) {
            $collection = [];

            for ($i = 0; $i < $factory->_times; ++$i) {
                $row = is_callable($callable) ? $callable($faker ?? Core::faker()) : $this->seeder($faker ?? Core::faker());
                $collection[] = $this->model(array_merge($row, $attrs))->save();
            }

            if (true === $toCollection) {
                $cb = function () use ($collection) {
                    foreach ($collection as $row) {
                        yield $row;
                    }
                };

                return $this->setEngine(Core::iterator($cb)->setModel($this));
            }

            return $collection;
        });

        return $factory;
    }

    /**
     * @param Faker|null $faker
     * @return array
     */
    protected function seeder(?Faker $faker = null)
    {
        $faker = $faker ?? Core::faker();

        return [];
    }

    /**
     * @param $conditions
     * @return Item|mixed|null
     */
    public function firstOrCreate($conditions)
    {
        $conditions = Core::arrayable($conditions) ? $conditions->toArray() : $conditions;

        foreach ($conditions as $field => $value) {
            $this->where($field, $value);
        }

        $this->setModel($this->getEngine()->getModel());

        if (null === ($row = $this->first())) {
            $row = $this->model($conditions)->save();
        }

        return $row;
    }

    /**
     * @param $conditions
     * @return Item|mixed|null
     */
    public function firstOrNew($conditions)
    {
        $conditions = Core::arrayable($conditions) ? $conditions->toArray() : $conditions;

        $q = $this;

        foreach ($conditions as $field => $value) {
            /** @var Iterator $q */
            $q = $q->where($field, $value);
        }

        $q->setModel($this->getEngine()->getModel());

        if (null === ($row = $q->first())) {
            $row = $this->model($conditions);
        }

        return $row;
    }

    /**
     * @param null $default
     * @return Item|mixed|null
     */
    public function firstOr($default = null)
    {
        if (null === ($row = $this->first())) {
            return $default;
        }

        return $row;
    }

    /**
     * @return Item|mixed|null
     */
    public function firstOrNull()
    {
        return $this->firstOr();
    }

    /**
     * @return Item|mixed|null
     */
    public function firstOrFalse()
    {
        return $this->firstOr(false);
    }


    /**
     * @return Item|mixed|null
     * @throws \Exception
     */
    public function firstOrFail()
    {
        if (false === ($row = $this->firstOr(false))) {
            throw new \Exception("Unable to find this row in database.");
        }

        return $row;
    }

    /**
     * @param $id
     * @return Item|null
     * @throws \Exception
     */
    public function findOrFail($id)
    {
        if (null === ($row = $this->find($id))) {
            throw new \Exception("Unable to find this row in database.");
        }

        return $row;
    }

    /**
     * @param array $ids
     * @return Iterator
     */
    public function withIds(array $ids = [])
    {
        $db = function () use ($ids) {
            foreach ($ids as $id) {
                if ($row = $this->find($id)) {
                    yield $row->toArray();
                }
            }
        };

        return Core::iterator($db)->setModel($this);
    }

    /**
     * @param bool $_cache
     * @return Db
     */
    public function setCache(bool $cache = true): self
    {
        $this->__cache = $cache;

        return $this;
    }

    public function useCache(bool $cache = true): self
    {
        return $this->setCache(true);
    }

    /**
     * @return bool
     */
    public function isCache(): bool
    {
        return $this->__cache;
    }

    public function getModeler(): ?string
    {
        return $this->modeler;
    }

    public function setModeler(?string $modeler = null): self
    {
        $this->modeler = $modeler;

        return $this;
    }

    public function switchResolver(Closure $resolver, callable $callable)
    {
        $old = $this->getResolver();

        $data = $callable($this->setResolver($resolver));

        $this->setResolver($old);

        return $data;
    }

    public function setResolver(?Closure $resolver = null): self
    {
        $this->__resolver = $resolver;

        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getResolver(): ?Closure
    {
        return $this->__resolver;
    }

    public function bulk(array $records)
    {
        foreach ($this->fire('bulk', $records, true) as $record) {
            $record->save();
        }
    }

    /**
     * @param mixed $concern
     * @return string
     */
    protected function serialize($concern)
    {
        return gzcompress(
            serialize(
                $concern
            )
        );
    }

    /**
     * @param mixed $concern
     * @return mixed
     */
    protected function unserialize($concern)
    {
        return unserialize(
            gzuncompress(
                $concern
            )
        );
    }

    /**
     * @param $name
     * @param callable $resolver
     * @return $this
     */
    public function with($name, callable $resolver): self
    {
        $this->withQuery[$name] = $resolver;

        return $this;
    }

    /**
     * @return array
     */
    public function getWithQuery(): array
    {
        return $this->withQuery;
    }

    /**
     * @param string $class
     * @param string $morphName
     * @param Item|null $record
     * @return Iterator
     */
    public function morphToMany(string $class, string $morphName, ?Item $record = null)
    {
        $record     = $record ?? Core::get('item_record');
        $keyName    = $morphName . '_type';
        $keyValue   = $morphName . '_id';

        /** @var Db $db */
        $db = new $class;

        return $db
            ->where($keyName, get_called_class())
            ->where($keyValue, $record['id'])
            ;
    }

    /**
     * @param string $class
     * @param string $morphName
     * @param Item|null $record
     * @return Item|null
     */
    public function morphToOne(string $class, string $morphName, ?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');

        return $this->morphToMany($record, $class, $morphName)->first();
    }

    /**
     * @param string|null $morphName
     * @param Item|null $record
     * @return Item|null
     */
    public function morphTo(?string $morphName = null, ?Item $record = null)
    {
        if (null === $morphName) {
            [,, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

            $morphName = $caller['function'];
        }

        $record = $record ?? Core::get('item_record');

        $morphedClass = $record[$morphName . '_type'];
        $morphedId = $record[$morphName . '_id'];

        /** @var Db $db */
        $db = new $morphedClass;

        return $db->find($morphedId);
    }

    /**
     * @param string $relation
     * @param string|null $fk
     * @return Iterator
     */
    public function has(string $relation, ?string $fk = null)
    {
        $concern = $fk ?? $this->getConcern(get_called_class()) . '_id';

        /** @var Db $model */
        $model = new $relation;

        return $model->setCache($this->isCache())->where($concern, '>', 0);
    }

    /**
     * @param string $relation
     * @param string|null $fk
     * @return Iterator
     */
    public function doesntHave(string $relation, ?string $fk = null)
    {
        $concern = $fk ?? $this->getConcern(get_called_class()) . '_id';

        /** @var Db $model */
        $model = new $relation;

        return $model->setCache($this->isCache())->where($concern, 'is', null);
    }

    /**
     * @param string $relation
     * @return string
     */
    public function getConcern(string $relation): string
    {
        return Str::lower(Core::uncamelize(Arr::last(explode('\\', $relation))));
    }

    public function withSelect($row)
    {
        $select = $this->getSelect();

        if (!empty($select)) {
            if (!in_array('id', $select)) {
                array_unshift($select, 'id');
            }

            $row = Arr::only($row, $select);
        }

        return $row;
    }

    /**
     * @param string $relation
     * @param string|null $fk
     * @param Item|null $record
     * @return mixed|Item|null
     */
    public function hasOne(string $relation, ?string $fk = null, ?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');

        return $this->hasMany($relation, $record, $fk)->first();
    }

    /**
     * @param string $relation
     * @param string|null $fk
     * @param Item|null $record
     * @return Iterator
     */
    public function hasMany(string $relation, ?string $fk = null, ?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');

        $concern = $fk ?? $this->getConcern(get_called_class()) . '_id';

        /** @var Db $model */
        $model = new $relation;

        return $model->where($concern, (int) $record['id']);
    }

    /**
     * @param string $relation
     * @param string|null $fk
     * @param Item|null $record
     * @return Db|Item|null
     */
    public function belongsTo(string $relation, ?string $fk = null, ?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');

        $concern = $fk ?? $this->getConcern($relation) . '_id';

        /** @var Db $model */
        $model = new $relation;

        return $model->find((int) $record[$concern]);
    }

    /**
     * @param string $relation
     * @param string|null $fk1
     * @param string|null $fk2
     * @param Item|null $record
     * @return Db|Item|null
     */
    public function belongsToMany(string $relation, ?string $fk1 = null, ?string $fk2 = null, ?Item $record = null)
    {
        $record = $record ?? Core::get('item_record');

        $concern1 = $fk1 ?? $this->getConcern(get_called_class()) . '_id';
        $concern2 = $fk2 ?? $this->getConcern($relation) . '_id';

        $pivotName = collect([ucfirst($this->getConcern(get_called_class())), ucfirst($this->getConcern($relation))])
            ->sort()->implode('');

        if (fnmatch('*_*_*', $pivotName)) {
            $first = $last = null;
            $all = explode('_', $pivotName);
            $count = count($all);
            $last = array_pop($all);

            for ($i = 0; $i < $count; ++$i) {
                $seg = $all[$i];

                if (fnmatch('*_*', $uncamelized = Core::uncamelize($seg))) {
                    $parts = explode('_', $uncamelized);
                    $first = current($parts);

                    break;
                }
            }

            $builder = [];

            if ($i > 0 && $i < $count - 1) {
                for ($j = 0; $j < $i; ++$j) {
                    $builder[] = Str::lower($all[$j]);
                }
            }

            $builder[] = $first;
            $builder[] = $last;

            $pivotName = ucfirst(Str::camel(implode('_', $builder)));
        }

        /** @var Db $model */
        $model = Core::getDb($pivotName);

        $ids = [];

        foreach ($model->where($concern1, (int) $record['id']) as $row) {
            $ids[] = (int) $row[$concern2];
        }

        /** @var Db $model */
        $model = new $relation;

        return $model instanceof Modeler ? $model::find($ids) : $model->find($ids);
    }

    /**
     * @param $key
     * @param null $operator
     * @param null $value
     * @param bool $returnIterator
     * @return Iterator|mixed|null
     */
    public function where($key, $operator = null, $value = null)
    {
        $this->__where = true;
        $this->__wheres[] = func_get_args();

        $nargs = func_num_args();

        $isCallable = 1 === func_num_args() && is_callable($key);

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

        $iterator   = $this->getEngine()->where($key, $operator, $value);
        $ids        = [];

        foreach ($iterator as $row) {
            $ids[] = $row['id'];
        }

        unset($iterator);

        return $this->withIds($ids);
    }

    public function pluckWhere(string $key, $value, string $pluckValue = 'id', ?string $pluckKey = null)
    {
        return $this->where($key, $value)->pluck($pluckValue, $pluckKey)->toArray();
    }

    /**
     * @param string $event
     * @param null $concern
     * @param bool $return
     *
     * @return mixed|null
     */
    public function fire(string $event, $concern = null, bool $return = false)
    {
        $methods = get_class_methods($this);
        $method  = Str::camel('on_' . $event);

        if (in_array($method, $methods)) {
            $result = $this->{$method}($concern);

            if ($return) {
                return $result;
            }
        } else {
            $observers = Core::get('itdb.observers', []);
            $self = get_called_class();

            $observer = Arr::get($observers, $self, Arr::get($observers, Modeler::class));

            if (null !== $observer) {
                $methods = get_class_methods($observer);

                if (in_array($event, $methods) || (count($methods) === 1 && in_array('__call', $methods))) {
                    $result = (new $observer)->{$event}($concern);

                    if ($return) {
                        return $result;
                    }
                }
            }
        }

        return $concern;
    }
}
