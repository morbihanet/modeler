<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;

class Record implements \IteratorAggregate, \ArrayAccess, \Countable
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public static function make($options = []): self
    {
        $options = (array) $options;
        $record = new static;

        foreach ($options as $key => $value) {
            if (is_array($value) && Arr::isAssoc($value)) {
                $record[$key] = static::make($value);
            } else {
                $record[$key] = $value;
            }
        }

        return $record;
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->options);
    }

    /**
     * @return \Generator
     */
    public function each()
    {
        foreach ($this->options as $option) {
            yield $option;
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->options);
    }

    /**
     * @return Iterator
     */
    public function iterator()
    {
        return Core::iterator([$this, 'each']);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function add(array $options = [])
    {
        $this->options = $this->options + $options;

        return $this;
    }

    /**
     * Return every entries.
     *
     * @return array
     */
    public function all()
    {
        return $this->options;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->options = [];

        return $this;
    }

    /**
     * Return an entry.
     *
     * @param $name
     * @param  null       $default
     * @return null|mixed
     */
    public function get($name, $default = null)
    {
        return value($this->options[$name] ?? $default);
    }

    /**
     * Check that an entry exists.
     *
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function merge(array $options)
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * @param $name
     * @return bool
     */
    public function remove($name)
    {
        if ($status = $this->has($name)) {
            unset($this->options[$name]);
        }

        return $status;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function set($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
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
     * @return bool
     */
    public function __isset($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function __get($offset)
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
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @param mixed $offset
     */
    public function __unset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->options);
    }

    public function first($default = null)
    {
        if (!$this->empty()) {
            $value = reset($this->options);

            return [key($this->options) => $value];
        }

        return $default;
    }

    public function last($default = null)
    {
        if (!$this->empty()) {
            $value = end($this->options);

            return [key($this->options) => $value];
        }

        return $default;
    }

    public function __call($name, $arguments)
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

        if ($class = Core::get('modeler')) {
            $model = app()->make($class);

            if (in_array($name, get_class_methods($model))) {
                return $model->{$name}(...$arguments);
            }
        }

        return $this->collection()->{$name}(...$arguments);
    }

    /**
     * @return bool
     */
    public function empty(): bool
    {
        return empty($this->options);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->options;
    }

    public function fullArray(): array
    {
        $data = $this->options;

        foreach ($data as $key => $value) {
            if ($value instanceof static) {
                $data[$key] = $value->fullArray();
            }
        }

        return $data;
    }

    /**
     * @param int $option
     * @return false|string
     */
    public function fullJson(int $option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->fullArray(), $option);
    }

    /**
     * @param int $option
     * @return false|string
     */
    public function toJson(int $option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->options, $option);
    }

    /**
     * @return false|string
     */
    public function fullString()
    {
        return $this->fullJson();
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
