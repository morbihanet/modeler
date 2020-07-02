<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Faker\Generator as Faker;
use Illuminate\Support\Traits\Macroable;

/**
 * @method Item|Iterator findOrFail($id)
 * @method Item|Iterator find($id, $default = null)
 * @method Item make(array $attributes = [])
 * @method Item firstOrNew(array $attributes, $values = [])
 * @method Item firstOrCreate(array $attributes, $values = [])
 * @method Item findOrNew($id, array $columns = [])
 * @method Item updateOrCreate($attributes, array $values = [])
 * @method Iterator customize(callable $callable)
 * @method Iterator notIn(string $column, array $values)
 * @method Iterator in(string $column, array $values)
 * @method Iterator orNotIn(string $column, array $values)
 * @method Iterator orIn(string $column, array $values)
 * @method Iterator whereIn(string $column, array $values)
 * @method Iterator orWhereIn(string $column, array $values)
 * @method Iterator whereNotIn(string $column, array $values, string $boolean = 'and')
 * @method Iterator orWhereNotIn(string $column, array $values)
 * @method Iterator orderBy(string $column, $direction = 'asc')
 * @method Iterator orderByDesc(string $column)
 * @method Iterator skip(int $value)
 * @method Iterator offset(int $value)
 * @method Iterator take(int $value)
 * @method Iterator limit(int $value)
 * @method Iterator forPage(int $page, int $perPage = 15)
 * @method Item|mixed firstOr(array $columns = [], $callback = null)
 * @method Iterator get(array $columns = [])
 * @method Iterator pluck(string $column, ?string $key = null)
 * @method Iterator cursor()
 * @method Iterator morphToMany(string $class, string $morphName, ?Item $record = null)
 * @method Iterator morphToOne(string $class, string $morphName, ?Item $record = null)
 * @method Iterator morphTo(?string $morphName = null, ?Item $record = null)
 * @method Iterator has(string $relation, ?string $fk = null)
 * @method Iterator doesntHave(string $relation, ?string $fk = null)
 * @method int destroy()
 * @method Iterator groupBy(string $groupBy, bool $preserveKeys = false)
 * @method Iterator where(string $column, ?string $operator = null, $value = null, string $boolean = 'and')
 * @method Iterator whereName($value)
 * @method Iterator whereId($value)
 * @method Iterator whereCreatedAt($value)
 * @method Iterator whereUpdatedAt($value)
 * @method Iterator like(string $column, string $value)
 * @method Iterator orLike(string $column, string $value)
 * @method Iterator notLike(string $column, string $value)
 * @method Iterator orNotLike(string $column, string $value)
 * @method Iterator likeI(string $column, string $value)
 * @method Iterator orLikeI(string $column, string $value)
 * @method Iterator notLikeI(string $column, string $value)
 * @method Iterator orNotLikeI(string $column, string $value)
 * @method bool contains(Item $item)
 * @method bool notContains(Item $item)
 * @method Iterator sortBy(string $column)
 * @method Iterator search(array $conditions)
 * @method Iterator sortByDesc(string $column)
 * @method Iterator orWhere(string $column, ?string $operator = null, $value = null)
 * @method Iterator latest(?string $column = null)
 * @method Iterator oldest(?string $column = null)
 * @method Iterator between(string $column, int $min, int $max)
 * @method Iterator orBetween(string $column, int $min, int $max)
 * @method Iterator isNull(string $column)
 * @method Iterator orIsNull(string $column)
 * @method Iterator isNotNull(string $column)
 * @method Iterator orIsNotNull(string $column)
 * @method Iterator startsWith(string $column, $value)
 * @method Iterator orStartsWith(string $column, $value)
 * @method Iterator endsWith(string $column, $value)
 * @method Iterator orEndsWith(string $column, $value)
 * @method Iterator notStartsWith(string $column, $value)
 * @method Iterator orNotStartsWith(string $column, $value)
 * @method Iterator notEndsWith(string $column, $value)
 * @method Iterator orNotEndsWith(string $column, $value)
 * @method Iterator lt(string $column, $value)
 * @method Iterator orLt(string $column, $value)
 * @method Iterator lte(string $column, $value)
 * @method Iterator orLte(string $column, $value)
 * @method Iterator gt(string $column, $value)
 * @method Iterator orGt(string $column, $value)
 * @method Iterator gte(string $column, $value)
 * @method Iterator orGte(string $column, $value)
 * @method Iterator before($date, bool $strict = true)
 * @method Iterator orBefore($date, bool $strict = true)
 * @method Iterator after($date, bool $strict = true)
 * @method Iterator orAfter($date, bool $strict = true)
 * @method Iterator when(string $field, $operator, $date)
 * @method Iterator orWhen(string $field, $operator, $date)
 * @method Iterator deleted()
 * @method Iterator orDeleted()
 * @method Iterator getEngine()
 * @method Iterator cacheFor(callable $callable, $time = '2 HOUR')
 * @method Iterator cacheForever(callable $callable)
 * @method int count($columns = '*')
 * @method int sync(Item $item, array $arguments = '*')
 * @method int attach(Item $item, array $arguments = '*')
 * @method int detach(Item $item)
 * @method Item|null first($columns = [])
 * @method Item|null firstBy(string $field, $value)
 * @method Item|null lastBy(string $field, $value)
 * @method Item|null findBy(string $field, $value = null)
 * @method bool updateOrInsert(array $attributes, array $values = [])
 * @method bool insert($values)
 * @method bool exists()
 * @method bool notExists()
 * @method mixed min(string $column)
 * @method mixed max(string $column)
 * @method mixed sum(string $column)
 * @method mixed avg(string $column)
 * @method mixed|null fire(string $event, $concern = null, bool $return = false)
 * @method Item create(array $attributes = [])
 * @method Modeler setCache(bool $cache = true)
 * @method Modeler setEngine(Iterator $engine)
 * @method Modeler select(...$fields)
 * @method string implode($value, ?string $glue = null)
 * @method Iterator chunk(int $size)
 * @method FileStore|Store|null|bool beginTransaction()
 * @method FileStore|Store|MemoryStore|RedisStore newQuery()
 * @method bool commit()
 * @method bool rollback()
 * @method string toJson()
 * @method Iterator all()
 * @method void proxy(string $method)
 * @method mixed transaction(\Closure $callback, int $attempts = 1)
 * @method array morphedByMany(string $morphClass, string $morphName)
 * @method Item|null morphed(string $morphClass, string $morphName)
 * @method array pluckWhere(string $key, $value, string $pluckValue = 'id', ?string $pluckKey = null)
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
 *
 * @mixin Iterator
 * @see Iterator
 */

class Modeler
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    protected static string $store = Store::class;
    public static string $connection = 'default';
    protected static array $booted = [];
    protected static array $rules = [];
    protected static array $seeders = [];
    protected bool $authenticable = false;

    public function __construct()
    {
        Core::set('modeler', $class = get_called_class());

        $hasBooted = isset(static::$booted[$class]);

        if (!$hasBooted) {
            static::boot();
        }
    }

    public static function defineSeeder($seeder)
    {
        static::$seeders[get_called_class()] = $seeder;
    }

    /**
     * @param string $observerClass
     * @return Db|FileStore|MemoryStore|RedisStore|Store
     */
    public static function observe(string $observerClass)
    {
        Core::set('modeler', $class = get_called_class());
        $model = static::getModelName($class);

        return static::factorModel($model)->observe($observerClass);
    }

    public static function observeAll(string $observerClass): void
    {
        $observers = Core::get('itdb.observers', []);
        $observers[static::class] = $observerClass;
        Core::set('itdb.observers', $observers);
    }

    public static function macro($name, $macro)
    {
        static::$macros[$name] = $macro;

        if ('boot' !== $name && !isset(static::$booted[get_called_class()])) {
            static::boot();
        }
    }

    protected static function boot()
    {
        Core::set('modeler_store', static::$store);

        $class = get_called_class();

        static::$booted[$class] = true;

        Event::fire('model:' . $class . ':booting');

        if (static::$store === MongoStore::class && !is_mongo($class) && !is_booting($class)) {
            is_booting($class, true);
            is_mongo($class, Str::lower(class_basename($class)));
        }

        Event::fire('model:' . $class . ':booted');

        if (static::hasMacro('boot')) {
            (new static)->macroCall('boot', []);
        }
    }

    /**
     * @param string $connection
     */
    public static function connection(string $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * @return string
     */
    public static function getStore(): string
    {
        return self::$store;
    }

    public function __get(string $key)
    {
        if ($item = Core::get('item_record')) {
            return $item[$key] ?? null;
        }

        return null;
    }

    public function isMongo(): ?string
    {
        return is_mongo(get_called_class());
    }

    public static function __callStatic($name, $arguments)
    {
        Core::set('modeler', $class = get_called_class());

        return Event::fire('modeler:'.$class.':' . $name, static::getDb()->{$name}(...$arguments));
    }

    public function __call($name, $arguments)
    {
        return static::__callStatic($name, $arguments);
    }

    public static function setStore(string $store)
    {
        static::$store = $store;
    }

    /**
     * @param callable|null $callable
     * @return Factory
     */
    public static function factory(?callable $callable = null): Macro
    {
        Core::set('modeler', $class = get_called_class());

        $hasBooted = isset(static::$booted[$class]);

        if (!$hasBooted) {
            static::boot();
        }

        $db = static::getDb();
        $factory = Macro::__instance('it_factory_' . get_class($db));

        $factory->_times = 1;

        $factory->new('times', function (int $t = 1) use ($factory) {
            $factory->_times = $t;

            return $factory;
        })->new('make', function (array $attrs = [], bool $toCollection = false, ?Faker $faker = null) use ($factory, $callable, $db) {
            $collection = [];
            $faker = $faker ?? Core::faker();

            for ($i = 0; $i < $factory->_times; ++$i) {
                $row = is_callable($callable) ? $callable($faker) : static::seeder($faker);
                $collection[] = $db->model(array_merge($row, $attrs));
            }

            if (true === $toCollection) {
                $cb = function () use ($collection) {
                    foreach ($collection as $row) {
                        yield $row;
                    }
                };

                return $db->setEngine(Core::iterator($cb)->setModel($db));
            }

            return $collection;
        })->new('create', function (array $attrs = [], bool $toCollection = false, ?Faker $faker = null) use ($factory, $callable, $db) {
            $collection = [];
            $faker = $faker ?? Core::faker();

            for ($i = 0; $i < $factory->_times; ++$i) {
                $row = is_callable($callable) ? $callable($faker) : static::seeder($faker);
                $collection[] = $db->model(array_merge($row, $attrs))->save();
            }

            if (true === $toCollection) {
                $cb = function () use ($collection) {
                    foreach ($collection as $row) {
                        yield $row;
                    }
                };

                return $db->setEngine(Core::iterator($cb)->setModel($db));
            }

            return $collection;
        });

        return $factory;
    }

    public static function bulk(array $rows)
    {
        $models = [];

        $db = static::getDb();

        foreach ($rows as $row) {
            $models[] = $db->create($row)['id'];
        }

        return Core::iterator(function () use ($models, $db) {
            foreach ($models as $id) {
                yield $db->find($id)->toArray();
            }
        })->setModel($db);
    }

    /**
     * @param Faker $faker
     * @return array
     */
    protected static function seeder(Faker $faker): array
    {
        $seeder = static::$seeders[get_called_class()] ?? [];

        if (is_callable($seeder)) {
            $seeder = $seeder($faker);
        }

        return $seeder;
    }

    public static function addBoot(callable $boot)
    {
        static::macro('boot', $boot);
    }

    /**
     * @return array
     */
    protected static function policies(): array
    {
        return [];
    }

    /**
     * @return array
     */
    protected static function rules(array $rules): array
    {
        static::$rules = array_merge(static::$rules, $rules);

        return static::$rules;
    }

    public static function validate(): ?array
    {
        if (!empty(static::$rules)) {
            $validator = Core::validator();

            return $validator->validate(request()->all(), static::$rules);
        }

        return null;
    }

    /**
     * @return Store|FileStore|RedisStore|MemoryStore
     */
    public static function getDb(bool $check = true)
    {
        $db = static::factorModel(static::getModelName(get_called_class()));

        if (true === $check && $last = Core::get('last_datum')) {
            /** @var static $last */
            if (class_basename($db) === class_basename($last)) {
                return $last->getDb(false);
            }
        }

        return $db->newQuery();
    }

    public static function getModelName(string $model)
    {
        if (fnmatch('*_*_*', $model)) {
            $first = $last = null;
            $all = explode('_', $model);
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

            return ucfirst(Str::camel(implode('_', $builder)));
        }

        return Str::lower(Core::uncamelize(Arr::last(explode('\\', $model))));
    }

    /**
     * @param string $model
     * @return Store|FileStore|RedisStore|MemoryStore
     */
    public static function factorModel(string $model)
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));
        $namespace = config('modeler.model_class', 'DB\\Models');
        $class = $namespace . '\\' . $model;

        if (!class_exists($class)) {
            $code = 'namespace ' . $namespace . ';';

            if (static::$store === Store::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\Store {}';
            } else if (static::$store === RedisStore::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\RedisStore {}';
            } else if (static::$store === MemoryStore::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\MemoryStore {}';
            } else if (static::$store === FileStore::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\FileStore {}';
            } else if (static::$store === LiteStore::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\LiteStore {}';
            } else if (static::$store === MongoStore::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\MongoStore {}';
            }

            eval($code);
        }

        return new $class;
    }

    /**
     * @param bool $authenticable
     * @return Modeler
     */
    public function setAuthenticable(bool $authenticable): Modeler
    {
        $this->authenticable = $authenticable;

        return $this;
    }
}
