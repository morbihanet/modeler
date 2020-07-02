<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Scope implements ArrayAccess
{
    protected string $scope = 'core';
    protected ?Modeler $store = null;

    public function __construct(string $scope = 'core')
    {
        $this->scope = $scope;
        $this->store = datum($scope, 'scope');
    }

    protected function makeKey(string $key): string
    {
        return Core::bearer() . '_' . $key;
    }

    public function offsetExists($key)
    {
        return $this->store->whereK($this->makeKey($key))->exists();
    }

    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    public function offsetGet($key)
    {
        if (isset($this[$key])) {
            return $this->store->whereK($this->makeKey($key))->first()->v;
        }

        return null;
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }

    public function offsetSet($key, $value)
    {
        $this->store->firstOrCreate(['k' => $this->makeKey($key)])->setV($value)->save();
    }

    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->store->whereK($this->makeKey($key))->deleteFirst();
    }

    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    public function setStore($store): self
    {
        $this->store = $store;

        return $this;
    }
}
