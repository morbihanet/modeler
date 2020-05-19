<?php
namespace Morbihanet\Modeler;

class I18n extends Valued
{
    protected ?string $locale = null;

    public function __construct(?string $locale = null)
    {
        parent::__construct();

        $this->locale = $locale ?? app()->getLocale();
    }

    public function offsetExists($offset)
    {
        return $this->whereK($this->locale . '.' . $offset)->first() instanceof Item;
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
            return value($this->whereK($this->locale . '.' . $offset)->first()->value('v'));
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
        $k = $this->locale . '.' . $k;

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

    public function __set(string $key, $value)
    {
        $this->offsetSet($key, $value);
    }

    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            $this->whereK($this->locale . '.' . $offset)->first()->delete();
        }
    }

    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }
}