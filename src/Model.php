<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Model extends Modeler implements ArrayAccess
{
    protected ?Item $item = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->item = static::getDb()->model($attributes);
    }

    public function offsetExists($offset)
    {
        return isset($this->item[$offset]);
    }

    public function __isset($offset)
    {
        return isset($this->item[$offset]);
    }

    public function offsetGet($offset)
    {
        $value = $this->item[$offset] ?? null;

        return value($value);
    }

    public function __get($offset)
    {
        $value = $this->item[$offset] ?? null;

        return value($value);
    }

    public function offsetSet($offset, $value)
    {
        $this->item[$offset] = $value;
    }

    public function __set($offset, $value)
    {
        $this->item[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->item[$offset]);
    }

    public function __unset($offset)
    {
        unset($this->item[$offset]);
    }

    public function itemClass(): string
    {
        return $this->item ? get_class($this->item) : Item::class;
    }

    public function dbClass(): string
    {
        return get_class(static::getDb());
    }

    public function newModel(array $attributes = []): Item
    {
        $class = $this->itemClass();

        return new $class(static::getDb(), $attributes);
    }

    public function __call(string $name, array $arguments)
    {
        $uncamelized = Core::uncamelize($name);

        if (
            $this->item instanceof Item && (
                in_array($name, get_class_methods($this->item)) ||
                fnmatch('set_*', $uncamelized) ||
                fnmatch('get_*', $uncamelized)
            )) {
            return $this->item->{$name}(...$arguments);
        }

        return parent::__call($name, $arguments);
    }
}