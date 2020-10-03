<?php

namespace Morbihanet\Modeler;

class Alias
{
    protected static array $aliases = [];

    public static function add(string $name, string $class)
    {
        static::$aliases[$name] = $class;
    }

    public static function autoload()
    {
        spl_autoload_register(function ($class) {
            if ($alias = static::$aliases[$class] ?? null) {
                if (!class_exists($class)) {
                    class_alias($alias, $class);
                }
            }
        });
    }
}
