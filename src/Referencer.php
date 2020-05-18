<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Referencer implements ArrayAccess
{
    protected ?object $target = null;

    public function __construct(object &$target)
    {
        $this->target = &$target;
    }

    public function referencer()
    {
        return Core::bind($this->target);
    }

    public function __set(string $key, $value)
    {
        $this->referencer()->{$key} = $value;
    }

    public function __isset(string $key)
    {
        return isset($this->referencer()->{$key});
    }

    public function __get(string $key)
    {
        $value = $this->referencer()->{$key} ?? null;

        return value($value);
    }

    public function __unset(string $key)
    {
        unset($this->referencer()->{$key});
    }

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->referencer()->{$name}(...$arguments);
    }
}