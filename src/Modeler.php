<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Faker\Generator as Faker;

/**
 * @method static Item|Iterator findOrFail($id)
 * @method static Item|Iterator find($id, $default = null)
 * @method static Item make($attributes = [])
 * @method static Item firstOrNew($attributes, $values = [])
 * @method static Item firstOrCreate($attributes, $values = [])
 * @method static Item findOrNew($id, $columns = [])
 * @method static Item updateOrCreate($attributes, $values = [])
 * @method static Iterator notIn(string $column, $values)
 * @method static Iterator in(string $column, $values)
 * @method static Iterator orNotIn(string $column, $values)
 * @method static Iterator orIn(string $column, $values)
 * @method static Iterator whereIn(string $column, $values)
 * @method static Iterator orWhereIn(string $column, $values)
 * @method static Iterator whereNotIn(string $column, $values, $boolean = 'and')
 * @method static Iterator orWhereNotIn(string $column, $values)
 * @method static Iterator orderBy(string $column, $direction = 'asc')
 * @method static Iterator orderByDesc(string $column)
 * @method static Iterator skip($value)
 * @method static Iterator offset($value)
 * @method static Iterator take($value)
 * @method static Iterator limit($value)
 * @method static Iterator forPage($page, $perPage = 15)
 * @method static Item|mixed firstOr($columns = [], $callback = null)
 * @method static Iterator get($columns = [])
 * @method static Iterator pluck($column, $key = null)
 * @method static Iterator cursor()
 * @method static Iterator morphToMany(string $class, string $morphName, ?Item $record = null)
 * @method static Iterator morphToOne(string $class, string $morphName, ?Item $record = null)
 * @method static Iterator morphTo(?string $morphName = null, ?Item $record = null)
 * @method static Iterator has(string $relation, ?string $fk = null)
 * @method static Iterator doesntHave(string $relation, ?string $fk = null)
 * @method static int destroy()
 * @method static Iterator groupBy($groupBy, $preserveKeys = false)
 * @method static Iterator where(string $column, $operator = null, $value = null, $boolean = 'and')
 * @method static Iterator like(string $column, $value)
 * @method static Iterator orLike(string $column, $value)
 * @method static Iterator notLike(string $column, $value)
 * @method static Iterator orNotLike(string $column, $value)
 * @method static Iterator likeI(string $column, $value)
 * @method static Iterator orLikeI(string $column, $value)
 * @method static Iterator notLikeI(string $column, $value)
 * @method static Iterator orNotLikeI(string $column, $value)
 * @method static bool contains(Item $item)
 * @method static bool notContains(Item $item)
 * @method static Iterator sortBy(string $column)
 * @method static Iterator search($conditions)
 * @method static Iterator sortByDesc(string $column)
 * @method static Iterator orWhere(string $column, $operator = null, $value = null)
 * @method static Iterator latest(?string $column = null)
 * @method static Iterator oldest(?string $column = null)
 * @method static Iterator between(string $column, int $min, int $max)
 * @method static Iterator orBetween(string $column, int $min, int $max)
 * @method static Iterator isNull(string $column)
 * @method static Iterator orIsNull(string $column)
 * @method static Iterator isNotNull(string $column)
 * @method static Iterator orIsNotNull(string $column)
 * @method static Iterator startsWith(string $column, $value)
 * @method static Iterator orStartsWith(string $column, $value)
 * @method static Iterator endsWith(string $column, $value)
 * @method static Iterator orEndsWith(string $column, $value)
 * @method static Iterator lt(string $column, $value)
 * @method static Iterator orLt(string $column, $value)
 * @method static Iterator lte(string $column, $value)
 * @method static Iterator orLte(string $column, $value)
 * @method static Iterator gt(string $column, $value)
 * @method static Iterator orGt(string $column, $value)
 * @method static Iterator gte(string $column, $value)
 * @method static Iterator orGte(string $column, $value)
 * @method static Iterator before($date, bool $strict = true)
 * @method static Iterator orBefore($date, bool $strict = true)
 * @method static Iterator after($date, bool $strict = true)
 * @method static Iterator orAfter($date, bool $strict = true)
 * @method static Iterator when(string $field, $operator, $date)
 * @method static Iterator orWhen(string $field, $operator, $date)
 * @method static Iterator deleted()
 * @method static Iterator orDeleted()
 * @method static Iterator getEngine()
 * @method static int count($columns = '*')
 * @method static int sync(Item $item, array $arguments = '*')
 * @method static int attach(Item $item, array $arguments = '*')
 * @method static int detach(Item $item)
 * @method static Item|null first($columns = [])
 * @method static Item|null firstBy(string $field, $value)
 * @method static Item|null lastBy(string $field, $value)
 * @method static Item|null findBy(string $field, $value = null)
 * @method static bool updateOrInsert(array $attributes, array $values = [])
 * @method static bool insert($values)
 * @method static bool exists()
 * @method static bool notExists()
 * @method static mixed min(string $column)
 * @method static mixed max(string $column)
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed|null fire(string $event, $concern = null, bool $return = false)
 * @method static Item create(array $attributes = [])
 * @method static Modeler setCache(bool $cache = true)
 * @method static Modeler setEngine(Iterator $engine)
 * @method static Modeler select(...$fields)
 * @method static string implode($value, $glue = null)
 * @method static Iterator chunk(int $size)
 * @method static FileStore|null|bool beginTransaction()
 * @method static bool commit()
 * @method static bool rollback()
 * @method static string toJson()
 * @method static void proxy(string $method)
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
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
 * @see Iterator
 */

class Modeler
{
    protected static string $store = Store::class;
    public static string $connection = 'default';
    protected static array $booted = [];
    protected bool $authenticable = false;

    public function __construct()
    {
        Core::set('modeler', $class = get_called_class());

        $hasBooted = isset(static::$booted[get_called_class()]);

        if (!$hasBooted) {
            static::boot();
        }
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

    protected static function boot()
    {
        //
    }

    public function __get(string $key)
    {
        if ($item = Core::get('item_record')) {
            return $item[$key] ?? null;
        }

        return null;
    }

    public static function __callStatic(string $name, array $arguments)
    {
        Core::set('modeler', $class = get_called_class());
        $model = static::getModelName($class);

        return static::factorModel($model)->{$name}(...$arguments);
    }

    public function __call(string $name, array $arguments)
    {
        Core::set('modeler', $class = get_called_class());
        $model = static::getModelName($class);

        return static::factorModel($model)->{$name}(...$arguments);
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
        $db = static::getDb();
        $factory = Macro::__instance('it_factory_' . get_class($db));

        $factory->_times = 1;

        $factory->new('times', function (int $t = 1) use ($factory) {
            $factory->_times = $t;

            return $factory;
        })->new('make', function (array $attrs = [], bool $toCollection = false, ?Faker $faker = null) use ($factory, $callable, $db) {
            $collection = [];

            for ($i = 0; $i < $factory->_times; ++$i) {
                $row = is_callable($callable) ? $callable($faker ?? Core::faker()) : static::seeder($faker ??
                    Core::faker());
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

            for ($i = 0; $i < $factory->_times; ++$i) {
                $row = is_callable($callable) ? $callable($faker ?? Core::faker()) : static::seeder($faker ??
                    Core::faker());
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

    /**
     * @param Faker $faker
     * @return array
     */
    protected static function seeder(Faker $faker): array
    {
        return [];
    }

    /**
     * @return array
     */
    protected static function policies(): array
    {
        return [];
    }

    /**
     * @return Store|FileStore|RedisStore|MemoryStore
     */
    public static function getDb()
    {
        return static::factorModel(static::getModelName(get_called_class()));
    }

    public static function getModelName(string $model)
    {
        if (fnmatch('*_*_*', $model)) {
            $dashes = explode('_', $model);
            $parts  = explode('_', $model, count($dashes) - 1);
            $suffix = array_shift($parts);
            $part   = array_pop($parts);
            $part   = str_replace($suffix, '', $part);

            return ucfirst(Str::camel(Str::lower($suffix) . '_' . $part));
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

        if (class_exists($class)) {
            return new $class;
        }

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
        }

        eval($code);

        return new $class;
    }
}
