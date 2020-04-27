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
        return static::interact($interaction, $parameters);
    }

    /**
     * @param  string  $interaction
     * @param  array  $parameters
     * @return mixed
     */
    public static function interact($interaction, array $parameters = [])
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
            if (in_array($method, get_class_methods($instance))) {
                return $instance->{$method}(...$parameters);
            }

            return $instance->{$method};
        };

        $closure->bindTo($instance, $instance);

        return app()->call($closure, $parameters);
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

        return app()->call($method, $parameters);
    }

    /**
     * @param $interaction
     * @param $callback
     * @return bool
     */
    public static function swap($interaction, $callback)
    {
        $status = isset(static::$interactions[$interaction]);

        static::$interactions[$interaction] = $callback;

        return $status === false;
    }
}
