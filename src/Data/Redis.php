<?php
namespace Morbihanet\Modeler\Data;

use ArrayAccess;
use Illuminate\Support\Str;
use Morbihanet\Modeler\Redis as Store;

class Redis implements ArrayAccess
{
    protected ?string $prefix = null;

    public function __construct(array $data = [])
    {
        $this->prefix = str_replace('\\', '.', Str::lower(get_called_class()));

        $this->fill($data);
    }

    public function fill(array $data = []): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return (int) Store::hexists($this->prefix, $key) > 0;
    }

    public function set(string $key, $value): self
    {
        Store::hset($this->prefix, $key, $this->serialize($value));

        return $this;
    }

    public function setnx(string $key, $value): self
    {
        Store::hsetnx($this->prefix, $key, $this->serialize($value));

        return $this;
    }

    public function get(string $key, $default = null)
    {
        return $this->has($key) ? $this->unserialize(Store::hget($this->prefix, $key)) : value($default);
    }

    public function remove(string $key): bool
    {
        if ($status = $this->has($key)) {
            Store::hdel($this->prefix, $key);
        }

        return $status;
    }

    protected function serialize($concern): string
    {
        return serialize($concern);
    }

    protected function unserialize($concern)
    {
        return unserialize($concern);
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    public function __isset($offset)
    {
        return $this->has($offset);
    }

    public function __get($offset)
    {
        return $this->get($offset);
    }

    public function __set($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function __unset($offset)
    {
        $this->remove($offset);
    }
}
