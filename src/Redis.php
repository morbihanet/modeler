<?php

namespace Morbihanet\Modeler;

use Predis\Client;

class Redis
{
    /** @var \Predis\Client */
    protected static $engine;
    protected static $instance;

    /**
     * @return \Predis\Client
     */
    public static function engine($engine = null)
    {
        if (null !== $engine) {
            static::$engine = $engine;

            return $engine;
        }

        if (null === static::$engine) {
            static::$engine = app('redis')->client();
        }

        return static::$engine;
    }

    /**
     * @param string $key
     * @param $value
     * @param string $time
     * @return mixed|null
     */
    public static function for(string $key, $value, string $time = '1 DAY')
    {
        $k = 'rfor.' . $key;

        if ($row = static::get($k)) {
            return unserialize($row);
        }

        $expire = strtotime('+' . $time) - time();
        $computed = value($value);

        static::set($k, serialize($computed));
        static::expire($k, $expire);

        return $computed;
    }

    /**
     * @param string $key
     * @param $value
     * @param string $time
     * @return mixed|null
     */
    public static function setFor(string $key, $value, string $time = '1 DAY')
    {
        $max = strtotime('+' . $time);
        $now = time();

        if ($row = static::hget('rsetfor', $key)) {
            $content = unserialize($row);

            if (isAke($content, 'time', $now - 1) < $now) {
                static::hdel('rsetfor', $key);
            } else {
                return $content['data'] ?? null;
            }
        }

        $content = ['time' => $max, 'data' => $computed = v($value)];

        static::hset('fastsetfor', $key, serialize($content));

        return $computed;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return static::engine()->{$name}(...$arguments);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return static::engine()->{$name}(...$arguments);
    }

    /**
     * @return Fast
     */
    public static function getInstance(): Fast
    {
        if (null === static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
    }
}
