<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Valued extends Modeler implements ArrayAccess
{
    protected static string $store = MemoryStore::class;
    protected static array $instances = [];


    public static function getInstance(): self
    {
        if (!$instance = static::$instances[$class = get_called_class()] ?? null) {
            $instance = new $class;
        }

        return $instance;
    }

    public function offsetExists($offset)
    {
        return $this->newQuery()->whereK($offset)->exists();
    }

    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->newQuery()->whereK($offset)->first()->value('v');
        }

        return null;
    }

    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function offsetSet($k, $v)
    {
        $this->newQuery()->firstOrCreate(compact('k'))->setV($v)->save();
    }

    public function __set(string $key, $value)
    {
        $this->offsetSet($key, $value);
    }

    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            $this->newQuery()->where('k', $offset)->first()->delete();
        }
    }

    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }

    /**
     * @param null|string|array $key
     * @param null|mixed $default
     * @return $this|Iterator|mixed|null
     */
    public function __invoke($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->newQuery()->cursor();
        }

        if (is_array($key)) {
            $this[$key[0]] = $key[1];

            return $this;
        }

        return $this[$key] ?? $default;
    }
}