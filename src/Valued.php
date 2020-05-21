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

    public function values()
    {
        return collect($this->select('k', 'v')->all()->toArray())->pluck('v', 'k')
        ->toArray();
    }

    public function offsetExists($offset)
    {
        return $this->whereK($offset)->first() instanceof Item;
    }

    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    public function has($offset)
    {
        return $this->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return value($this->whereK($offset)->first()->value('v'));
        }

        return null;
    }

    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function get($offset, $default = null)
    {
        return $this->offsetGet($offset) ?? value($default);
    }

    public function offsetSet($k, $v)
    {
        /** @var null|Item $row */
        if ($row = $this->whereK($k)->first()) {
            $row->update(compact('v'));
        } else {
            $this->create(compact('k', 'v'));
        }
    }

    public function set(string $key, $value): self
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    public function manySet(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function manyGet(...$keys)
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    public function __set(string $key, $value)
    {
        $this->offsetSet($key, $value);
    }

    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            $this->whereK($offset)->first()->delete();
        }
    }

    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }

    public function remove($offset): bool
    {
        $status = $this->offsetExists($offset);

        $this->offsetUnset($offset);

        return $status;
    }

    public function delete($offset): bool
    {
        return $this->remove($offset);
    }

    /**
     * @param null|string|array $key
     * @param null|mixed $default
     * @return $this|Iterator|Item[]|mixed|null
     */
    public function __invoke($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->cursor();
        }

        if (is_array($key)) {
            $this[$key[0]] = $key[1];

            return $this;
        }

        return $this[$key] ?? $default;
    }
}
