<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;

class CacheFacade
{
    protected static string $storeClass = Warehouse::class;

    public static function self()
    {
        return new Cache(str_replace('\\', '_', Str::lower(get_called_class())));
    }

    public static function __callStatic(string $method, array $arguments)
    {
        return static::self()->{$method}(...$arguments);
    }

    public function __call(string $method, array $arguments)
    {
        return static::self()->{$method}(...$arguments);
    }
}