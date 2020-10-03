<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Lazy implements ArrayAccess
{
    protected static array $lazies = [];
    protected static array $instances = [];
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

    public function get(string $key, $default)
    {
        return value(static::$lazies[$this->makeKey($key)] ?? $default);
    }

    public function offsetExists($offset)
    {
        return array_key_exists($this->makeKey($offset), static::$lazies);
    }

    public function offsetGet($offset)
    {
        return value(static::$lazies[$this->makeKey($offset)] ?? null);
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
