<?php
namespace Morbihanet\Modeler;

use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

class Context implements ArrayAccess
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    protected array $values = [];
    protected string $context= 'core';

    protected static array $instances = [];

    public function __construct(string $context = 'core', array $attributes = [])
    {
        $this->fill($attributes);
        $this->context = $context;

        static::$instances[$context] = $this;
    }

    public static function getInstance(string $context = 'core', array $attributes = []): Context
    {
        if (!isset(static::$instances[$context])) {
            new static($context, $attributes);
        }

        return static::$instances[$context];
    }

    public function merge(array $toMerge): self
    {
        $this->values = array_merge($this->values, $toMerge);

        return $this;
    }

    public function setNx(string $key, $value): self
    {
        if (!isset($this->values[$key])) {
            $this->values[$key] = $value;
        }

        return $this;
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function toJson(int $option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->values, $option);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function __isset(string $key)
    {
        return isset($this->values[$key]);
    }

    public function has(string $key): bool
    {
        return $this->__isset($key);
    }

    public function __get(string $key)
    {
        $value = $this->values[$key] ?? null;

        return value($value);
    }

    public function get(string $key, $default = null)
    {
        $value = $this->values[$key] ?? $default;

        return value($value);
    }

    public function __unset(string $key)
    {
        unset($this->values[$key]);
    }

    public function remove(string $key)
    {
        if ($status = $this->has($key)) {
            $this->__unset($key);
        }

        return $status;
    }

    public function __set(string $key, $value)
    {
        $this->values[$key] = $value;
    }

    public function set(string $key, $value)
    {
        $this->__set($key, $value);

        return $this;
    }

    public function offsetExists($key): bool
    {
        return $this->__isset($key);
    }

    public function offsetGet($key)
    {
        return $this->__get($key);
    }

    public function offsetSet($key, $value)
    {
        $this->__set($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->__unset($key);
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): Context
    {
        $this->context = $context;

        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        if (self::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        if (substr($name, 0, 3) === 'set' && strlen($name) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($name, 3)));
            $field              = Str::lower($uncamelizeMethod);
            $value              = array_shift($arguments);

            return $this->set($field, $value);
        }

        if (substr($name, 0, 3) === 'get' && strlen($name) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($name, 3)));
            $field              = Str::lower($uncamelizeMethod);
            $def                = value(array_shift($arguments));

            return $this->get($field, $def);
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
    }
}
