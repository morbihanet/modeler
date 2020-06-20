<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin  \Morbihanet\Modeler\Model
 */

class Table extends Facade
{
    protected static array $datums = [];

    public static function getFacadeAccessor()
    {
        $class = Str::lower(str_replace('\\', '.', get_called_class()));
        $parts = explode('.', $class);

        $model = array_pop($parts);
        $db = implode('_', $parts);
        $key = ucfirst(Str::camel($db . '_' . $model));

        if (!$datum = static::$datums[$key] ?? null) {
            $datum = datum($model, $db);
            static::$datums[$key] = $datum;
        }

        return $datum->newQuery();
    }
}
