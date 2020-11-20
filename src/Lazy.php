<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Lazy implements ArrayAccess
{
    protected static array $lazies = [];
    protected static array $instances = [];
    protected static array $parameters = [];
    protected ?string $namespace = null;

    public function __construct(string $namespace = 'core')
    {
        $this->namespace = $namespace;
    }

    public static function getInstance(string $namespace = 'core'): self
    {
        if (!$instance = static::$instances[$namespace] ?? null) {
            $instance = static::$instances[$namespace] = new static($namespace);
        }

        return $instance;
    }

    protected function makeKey(string $key): string
    {
        return $this->namespace . '.' . $key;
    }

    public function has(string $key): bool
    {
        return $this->offsetExists($key);
    }

    public function delete(string $key): bool
    {
        if ($status = $this->has($key)) {
            $this->offsetUnset($key);
        }

        return $status;
    }

    public function set(string $key, $value): self
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    public function factory(string $key, callable $value, ...$parameters): self
    {
        $this->offsetSet($key, $value);

        static::$parameters[$this->makeKey($key)] = $parameters;

        return $this;
    }

    public function singleton(string $key, callable $value, ...$parameters): self
    {
        $this->offsetSet($key, $value(...$parameters));

        return $this;
    }

    public function get(string $key, $default = null)
    {
        $get = static::$lazies[$this->makeKey($key)] ?? $default;

        if (is_callable($get)) {
            $params = static::$parameters[$this->makeKey($key)] ?? [];

            return $get(...$params);
        }

        return value($default);
    }

    public function once(string $key, $default = null)
    {
        $value = value(static::$lazies[$this->makeKey($key)] ?? $default);

        static::$lazies[$this->makeKey($key)] = $value;

        return $value;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($this->makeKey($offset), static::$lazies);
    }

    public function offsetGet($offset)
    {
        $get = static::$lazies[$this->makeKey($offset)] ?? null;

        if (is_callable($get)) {
            $params = static::$parameters[$this->makeKey($offset)] ?? [];

            return $get(...$params);
        }

        return $get;
    }

    public function offsetSet($offset, $value)
    {
        static::$lazies[$this->makeKey($offset)] = $value;
    }

    public function offsetUnset($offset)
    {
        unset(static::$lazies[$this->makeKey($offset)]);
    }
}
