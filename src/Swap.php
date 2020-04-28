<?php
namespace Morbihanet\Modeler;

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
            } catch (\Exception $e) {
                return Core::bind($instance)->{$method};
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
    public static function define(string $interaction, $callback)
    {
        return static::swap($interaction, $callback);
    }

    /**
     * @param string $interaction
     * @param $callback
     * @return bool
     */
    public static function swap(string $interaction, $callback)
    {
        $status = isset(static::$interactions[$interaction]);

        static::$interactions[$interaction] = $callback;

        return $status === false;
    }
}
