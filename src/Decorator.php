<?php
namespace Morbihanet\Modeler;

use stdClass;

class Decorator
{
    protected static string $decored = stdClass::class;
    protected static array $instances = [];

    public static function getInstance()
    {
        return static::$instances[static::$decored] ?? new stdClass;
    }

    public static function setInstance($instance)
    {
        static::$instances[static::$decored] = $instance;
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return static::$decored::{$name}(...$arguments);
    }

    public function __call(string $name, array $arguments)
    {
        return static::getInstance()->{$name}(...$arguments);
    }
}