<?php

namespace Morbihanet\Modeler;

use Countable;
use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Store;
use Morbihanet\Modeler\Store as Driver;
use Illuminate\Support\Traits\Macroable;

class Cache implements Store, ArrayAccess, Countable
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * @var Driver
     */
    private $store;

    public function __construct(string $namespace = 'core')
    {
        $this->store = (new Driver)->setNamespace('dbcache.' . $namespace);
    }

    /**
     * @param string $namespace
     * @return Cache
     */
    public function setNamespace(string $namespace = 'core'): self
    {
        $this->store = (new Driver)->setNamespace('dbcache.' . $namespace);

        return $this;
    }

    /**
     * @param string $namespace
     * @return Cache
     */
    public static function make(string $namespace = 'core'): self
    {
        return new static($namespace);
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string|array $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->store[$key] ?? null;
    }

    /**
     * @param string $key
     * @param mixed $otherwise
     * @return mixed|null
     */
    public function getOr(string $key, $otherwise = null)
    {
        return $this->store[$key] ?? value($otherwise);
    }

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        return tap($this->getOr($key, $default), function () use ($key) {
            $this->forget($key);
        });
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param array $keys
     * @return array
     */
    public function many(array $keys)
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    /**
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern = '*'): array
    {
        return $this->store->keys($pattern);
    }

    /**
     * @param string $pattern
     * @param string $search
     * @return array
     */
    public function search(string $pattern = '*', string $search = '*'): array
    {
        return $this->store->search($pattern, $search);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->store->getAll();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->all();
    }


    /**
     * @return Collection
     */
    public function toCollection()
    {
        return collect($this->all());
    }

    /**
     * @return string
     */
    public function toJson($option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->all(), $option);
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function put($key, $value, $seconds = 0)
    {
        $this->store->expire($key, $value, $seconds / 60);

        return true;
    }

    /**
     * @param $key
     * @param $value
     * @param int $seconds
     * @return Cache
     */
    public function set($key, $value, $seconds = 0): self
    {
        $this->store->expire($key, $value, $seconds / 60);

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @param string $time
     * @return mixed|null
     */
    public function setFor(string $key, $value, string $time = '1 DAY')
    {
        if (null === ($val = $this->get($key))) {
            $seconds = strtotime('+' . $time) - time();
            $this->store->expire($key, $val = value($value), $seconds / 60);
        }

        return $val;
    }

    /**
     * @param string $key
     * @param $value
     * @return mixed|null
     */
    public function setForever(string $key, $value)
    {
        return $this->setFor($key, $value, '10 YEAR');
    }

    /**
     * @param $key
     * @param $value
     * @param int $seconds
     * @return DBCache
     */
    public function add($key, $value, $seconds = 0): self
    {
        if (!$this->has($key)) {
            $this->store->expire($key, $value, $seconds / 60);
        }

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        return isset($this->store[$key]);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function missing(string $key)
    {
        return !$this->has($key);
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array $values
     * @param int $seconds
     * @return bool
     */
    public function putMany(array $values, $seconds = 0)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    /**
     * @param $values
     * @param int $seconds
     * @return bool
     */
    public function setMultiple($values, $seconds = 0)
    {
        return $this->putMany($values, $seconds);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->store->incr($key, 0, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->store->decr($key, 0, $value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever($key, $value)
    {
        $this->store[$key] = $value;

        return true;
    }

    /**
     * @param string $key
     * @param int $minutes
     * @param $callback
     * @return mixed|null
     */
    public function remember(string $key, int $minutes, $callback)
    {
        if ('mambodummy' === ($value = $this->getOr($key, 'mambodummy'))) {
            $this->store->expire($key, $value = value($callback), $minutes);
        }

        return $value;
    }

    /**
     * @param string $key
     * @param $callback
     * @param string $time
     * @return mixed|null
     */
    public function rememberFor(string $key, $callback, string $time)
    {
        $minutes = (strtotime('+ ' . $time) - time()) / 60;

        return $this->remember($key, $minutes, $callback);
    }

    /**
     * @param string $key
     * @param $callback
     * @return mixed|null
     */
    public function rememberForever(string $key, $callback)
    {
        return $this->sear($key, $callback);
    }

    /**
     * @param string $name
     * @param \Closure $closure
     * @param int $timestamp
     * @param mixed ...$args
     * @return mixed|null
     */
    public function until(string $name, \Closure $closure, int $timestamp, ...$args)
    {
        $db     = new self('untils');
        $row    = $db->getOr($name, []);

        $when    = $row['when'] ?? null;
        $value   = $row['value'] ?? 'mambodummy';

        if (null !== $when && 'mambodummy' !== $value) {
            $when = (int) $when;

            if ($timestamp === $when) {
                return $value;
            }
        }

        $data = $closure(...$args);

        $row['when'] = $timestamp;
        $row['value'] = $data;
        $db->set($name, $row);

        return $data;
    }

    /**
     * @param string $key
     * @param $callback
     * @return mixed|null
     */
    public function sear(string $key, $callback)
    {
        if ('mambodummy' === ($value = $this->getOr($key, 'mambodummy'))) {
            $value = value($callback);
            $this->store[$key] = $value;
        }

        return $value;
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget($key)
    {
        if (true === ($status = $this->has($key))) {
            unset($this->store[$key]);
        }

        return $status;
    }

    /**
     * @param string $key
     * @param \Closure|null $after
     * @return bool
     */
    public function delete(string $key, ?\Closure $after = null)
    {
        if (true === ($status = $this->forget($key))) {
            if ($after instanceof \Closure) {
                $after->bindTo($this, $this);
                $after($key);
            }
        }

        return $status;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $this->store->flush();

        return empty($this->store->toArray());
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return '';
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|void
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->all());
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return 0 === $this->count();
    }

    /**
     * @param null $default
     * @return array|null
     */
    public function first($default = null)
    {
        if (!$this->isEmpty()) {
            $data = $this->toArray();

            $value = reset($data);

            return [key($data) => $value];
        }

        return $default;
    }

    /**
     * @param null $default
     * @return array|null
     */
    public function last($default = null)
    {
        if (!$this->isEmpty()) {
            $data = $this->toArray();

            $value = end($data);

            return [key($data) => $value];
        }

        return $default;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function merge(array $options): self
    {
        return $this->setMany(array_merge($this->toArray(), $options));
    }

    /**
     * @return \Generator
     */
    public function each()
    {
        foreach ($this->toArray() as $key => $value) {
            yield [$key => $value];
        }
    }

    /**
     * @param array $keys
     * @param int $expire
     * @return Cache
     */
    public function setMany(array $keys, int $expire = 0): self
    {
        foreach ($keys as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue, $expire);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (static::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        if (substr($name, 0, 3) === 'set' && strlen($name) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($name, 3)));
            $field              = Str::lower($uncamelizeMethod);

            $v = array_shift($arguments);

            return $this->set($field, $v);
        }

        if (substr($name, 0, 3) === 'get' && strlen($name) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($name, 3)));
            $field              = Str::lower($uncamelizeMethod);

            $d = array_shift($arguments);

            return $this->get($field, $d);
        }

        if (substr($name, 0, 3) === 'has' && strlen($name) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($name, 3)));
            $field              = Str::lower($uncamelizeMethod);

            return $this->has($field);
        }

        if (substr($name, 0, 3) === 'del' && strlen($name) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($name, 3)));
            $field              = Str::lower($uncamelizeMethod);

            return $this->remove($field);
        }

        return $this->toCollection()->{$name}(...$arguments);
    }

    /**
     * @return Driver
     */
    public function getStore(): Driver
    {
        return $this->store;
    }

    public function memory()
    {
        $this->store->getConnection()->getPdo()->exec("create table kv(
            k text not null
                primary key,
            v text null,
            e integer default '0' not null,
            called_at text null
        )");
    }
}
