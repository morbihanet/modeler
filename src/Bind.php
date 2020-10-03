<?php

namespace Morbihanet\Modeler;

use Closure;
use Exception;
use ArrayAccess;
use ReflectionFunction;
use Illuminate\Support\Traits\Macroable;

class Bind implements ArrayAccess
{
    use Macroable;

    /**
     * @var Closure
     */
    private $__bind;

    /**
     * @var string
     */
    private $__instanceOf;

    /**
     * @var array
     */
    protected $__called = [];

    /**
     * @param $name
     * @param $macro
     * @return $this
     */
    public function mockify($name, $macro)
    {
        static::$macros[$name] = $macro;

        return $this;
    }

    public function bindStealCall($target, string $method, array $parameters)
    {
        $macro = static::$macros[$method] ?? null;

        if ($macro instanceof Closure) {
            try {
                return call_user_func_array($macro->bindTo($target, get_class($target)), $parameters);
            } catch (Exception $e) {
                return call_user_func_array($macro, array_merge($parameters, [$target]));
            }
        }

        return null;
    }

    protected function __called(string $method): self
    {
        if (!isset($this->__called[$method])) {
            $this->__called[$method] = 0;
        }

        ++$this->__called[$method];

        return $this;
    }

    public function hasBeenCalled(string $method): bool
    {
        return isset($this->__called[$method]) && 0 < $this->__called[$method];
    }

    public function timesCalled(string $method): int
    {
        if (isset($this->__called[$method])) {
            return $this->__called[$method];
        }

        return 0;
    }

    /**
     * @param object|string $target
     * @param mixed ...$parameters
     */
    public function __construct($target, ...$parameters)
    {
        if (is_string($target) && class_exists($target)) {
            $target = new $target(...$parameters);
        }

        $this->__bind = $this->__bindify($target);
    }

    public function __get($key)
    {
        $callable = $this->__bind;

        return $callable($key);
    }

    public function __set($key, $value)
    {
        $ref = new ReflectionFunction($this->__bind);

        try {
            $property = $ref->getClosureScopeClass()->getProperty($key);
            $property->setAccessible(true);
            $property->setValue($this->__value($this->__bind), $value);
        } catch (Exception $e) {
            $this->__value($this->__bind)->{$key} = $value;
        }
    }

    public function __isset($key): bool
    {
        return null !== $this->__get($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __unset($key)
    {
        $status = $this->__isset($key);

        if (true === $status) {
            $callable = $this->__bind;
            $callable()->{$key} = null;
            unset($callable()->{$key});
        }

        return $status;
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = $this->__bind;

        if ($this->__called($method)->hasMacro($method)) {
            return $this->bindStealCall($callable(), $method, $parameters);
        }

        return $callable($method, ...$parameters);
    }

    /**
     * @param mixed $offset
     * @return bool|void
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|void
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * @return string
     */
    public function getInstanceOf(): string
    {
        return $this->__instanceOf;
    }

    /**
     * @return mixed
     */
    public function __reveal()
    {
        return $this->__value($this->__bind);
    }

    /**
     * @param object $target
     * @param callable $callback
     * @return object
     */
    public static function mocker(object $target, callable $callback)
    {
        $bind = new static($target);

        $callback($bind);

        return $target;
    }

    /**
     * @param object $object
     * @return Closure
     */
    protected function __bindify($object): Closure
    {
        $this->__instanceOf = get_class($object);

        return Closure::bind(function (...$parameters) use ($object) {
            $method = array_shift($parameters);

            if (null === $method) {
                return $object;
            }

            if (in_array($method, get_class_methods($object))) {
                return $object->{$method}(...$parameters);
            }

            return $object->{$method};
        }, null, $this->__instanceOf);
    }

    /**
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    protected function __value($value, ...$args)
    {
        return is_callable($value) ? $value(...$args) : $value;
    }
}
