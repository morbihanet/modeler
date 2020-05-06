<?php
namespace Morbihanet\Modeler;

use Closure;

class Accessor
{
    protected static array $resolvers = [];
    protected static array $instances = [];

    public static function resolver($resolver = null)
    {
        if (null !== $resolver) {
            static::$resolvers[get_called_class()] = $resolver;
        }

        return value(static::$resolvers[get_called_class()]);
    }

    public static function macro(string $name, $macro)
    {
        static::$macros[get_called_class() . $name] = $macro;
    }

    public static function hasMacro(string $name)
    {
        return isset(static::$macros[get_called_class() . $name]);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (static::hasMacro($name)) {
            $macro = static::$macros[get_called_class() . $name];

            if ($macro instanceof Closure) {
                return call_user_func_array(Closure::bind($macro, null, get_called_class()), $arguments);
            }

            return $macro(...$arguments);
        }

        return static::resolver()->{$name}(...$arguments);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return static::__callStatic($name, $arguments);
    }

    public static function getInstance(): self
    {
        if (!isset(static::$instances[get_called_class()])) {
            static::$instances[get_called_class()] = new static;
        }

        return static::$instances[get_called_class()];
    }
}