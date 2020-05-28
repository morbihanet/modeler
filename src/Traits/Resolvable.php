<?php
namespace Morbihanet\Modeler\Traits;

use Illuminate\Support\Str;
use Morbihanet\Modeler\Core;

trait Resolvable
{
    protected static $resolver = null;

    public static function self()
    {
        $method  = Str::lower(class_basename($class = get_called_class()));
        $methods = get_class_methods($class);

        if (in_array('boot', $methods)) {
            static::boot();
        }

        $resolver = static::getResolver();

        if (is_callable($resolver)) {
            return $resolver();
        }

        if (in_array('constructor', $methods)) {
            static::setResolver(function () use ($method) {
                return Core::$method(...static::constructor());
            });
        } else {
            static::setResolver(function () use ($method) {
                return Core::$method();
            });
        }

        return static::self();
    }

    public function __call(string $name, array $arguments)
    {
        return static::{$name}(...$arguments);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        $self = static::self();

        return $self::{$name}(...$arguments);
    }

    public static function getResolver()
    {
        return static::$resolver;
    }

    public static function setResolver($resolver): void
    {
        static::$resolver = $resolver;
    }
}
