<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Eloquent\Collection;

/**
 * @method static Item|Collection findOrFail($id)
 * @method static Item|Collection find($id, $default = null)
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
 * @method static Iterator contains(string $column, $value = null)
 * @method static Iterator notContains(string $column, $value = null)
 * @method static Iterator orContains(string $column, $value = null)
 * @method static Iterator orNotContains(string $column, $value = null)
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
 * @method static Item|object|null first($columns = [])
 * @method static Item|object|null firstBy(string $field, $value)
 * @method static Item|object|null lastBy(string $field, $value)
 * @method static Item|object|null findBy(string $field, $value = null)
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
 * @method static Macro factory(?callable $callable = null)
 * @method static bool beginTransaction()
 * @method static bool commit()
 * @method static bool rollback()
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 *
 * @see Iterator
 */

class Modeler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return static::factorModel(class_basename(get_called_class()));
    }

    public static function factorModel(string $model)
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));
        $namespace = config('modeler.model_class', 'DB\\Models');

        $class = $namespace . '\\' . $model;

        if (class_exists($class)) {
            return new $class;
        }

        $code = 'namespace ' . $namespace . ';';
        $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\Store {}';

        eval($code);

        return new $class;
    }
}
