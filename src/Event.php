<?php
namespace Morbihanet\Modeler;

class Event
{
    protected static array $cancelled = [];

    /**
     * @param string $name
     * @param callable $callable
     */
    public static function add(string $name, callable $callable): void
    {
        $events = Core::get('core.events', []);
        $events[$name] = $callable;
        Core::set('core.events', $events);
    }

    public static function listen(string $name, callable $callable): void
    {
        static::add($name, $callable);
    }

    public static function off(string $name): void
    {
        if (static::has($name) && !isset(static::$cancelled[$name])) {
            static::$cancelled[$name] = true;
        }
    }

    public static function on(string $name): void
    {
        if (static::has($name) && isset(static::$cancelled[$name])) {
            unset(static::$cancelled[$name]);
        }
    }

    public static function has(string $name): bool
    {
        $events = Core::get('core.events', []);

        return isset($events[$name]);
    }

    public static function remove(string $name): bool
    {
        $events = Core::get('core.events', []);

        if (isset($events[$name])) {
            unset($events[$name]);
            Core::set('core.events', $events);

            return true;
        }

        return false;
    }

    public static function in(string $class, array $methods)
    {
        foreach ($methods as $event => $callable) {
            static::add($class . '.' . $event, $callable);
        }
    }

    public static function try(string $name, ...$params)
    {
        $otherwise = array_pop($params);

        if (is_callable($otherwise)) {
            if ($callable = static::getCallable($name)) {
                return $callable(...$params);
            }

            return $otherwise(...$params);
        }

        return null;
    }

    public static function hook(string $name, ...$params)
    {
        if ($callable = static::getCallable($name)) {
            return $callable(...$params);
        }

        return null;
    }

    public static function fire(string $name, $concern = null, bool $return = false)
    {
        if ($callable = static::getCallable($name)) {
            $result = $callable($concern);

            if (true === $return) {
                return $result;
            }
        }

        return $concern;
    }

    protected static function getCallable(string $name): ?callable
    {
        if (!isset(static::$cancelled[$name])) {
            $events = Core::get('core.events', []);

            $callable = $events[$name] ?? null;

            return is_callable($callable) ? $callable : null;
        }

        return null;
    }
}
