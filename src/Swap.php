<?php
namespace Morbihanet\Modeler;

use Exception;
use Illuminate\Support\Str;

class Swap
{
    /**
     * @var array
     */
    public static $interactions = [];

    /**
     * @param $interaction
     * @param mixed ...$parameters
     * @return mixed
     */
    public static function call($interaction, ...$parameters)
    {
        return static::interact($interaction, ...$parameters);
    }

    /**
     * @param  string  $interaction
     * @param  array  $parameters
     * @return mixed
     */
    public static function interact($interaction, ...$parameters)
    {
        if (!Str::contains($interaction, '@')) {
            $interaction = $interaction.'@handle';
        }

        [$class, $method] = explode('@', $interaction);

        if (isset(static::$interactions[$interaction])) {
            return static::callSwappedInteraction($interaction, $parameters, $class);
        }

        $base = class_basename($class);

        if (isset(static::$interactions[$base.'@'.$method])) {
            return static::callSwappedInteraction($base.'@'.$method, $parameters, $class);
        }

        $instance = app()->make($class);

        $closure = function () use ($instance, $method, $parameters) {
            try {
                return Core::bind($instance)->{$method}(...$parameters);
            } catch (Exception $e) {
                return $instance->{$method} ?? null;
            }
        };

        $closure->bindTo($instance, $instance);

        return value($closure);
    }

    /**
     * @param $interaction
     * @param array $parameters
     * @param string $class
     * @return mixed
     */
    protected static function callSwappedInteraction(string $interaction, array $parameters, string $class)
    {
        if (is_string(static::$interactions[$interaction])) {
            return static::interact(static::$interactions[$interaction], $parameters);
        }

        $instance = app()->make($class);

        $method = static::$interactions[$interaction]->bindTo($instance, $instance);

        return $method(...$parameters);
    }

    /**
     * @param string $interaction
     * @param $callback
     * @return bool
     */
    public static function define(string $interaction, $callback): bool
    {
        return static::swap($interaction, $callback);
    }

    public static function in(string $class, array $methods)
    {
        foreach ($methods as $method => $swap) {
            static::apply($class . '@' . $method, $swap);
        }
    }

    public static function apply(string $interaction, $callback): bool
    {
        if (!$status = isset(static::$interactions[$interaction])) {
            static::$interactions[$interaction] = $callback;
        }

        return !$status;
    }

    public static function swap(string $interaction, $callback): bool
    {
        $status = isset(static::$interactions[$interaction]);

        static::$interactions[$interaction] = $callback;

        return !$status;
    }
}
