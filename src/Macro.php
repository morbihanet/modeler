<?php
namespace Morbihanet\Modeler;

use ArrayAccess;

class Macro implements ArrayAccess
{
    /**
     * @var array
     */
    private static $macros = [];

    /**
     * @var array
     */
    private static $instances = [];

    /**
     * @var string
     */
    private $namespace;

    /**
     * @param string $namespace
     */
    public function __construct(string $namespace = 'core')
    {
        $this->namespace = $namespace;
    }

    public function __set(string $key, $value)
    {
        $this->{$key} = $value;
    }

    public function __get(string $key)
    {
        return $this->{$key} ?? null;
    }

    public function __isset(string $key)
    {
        return isset($this->{$key});
    }

    public function __unset(string $key)
    {
        unset($this->{$key});
    }

    /**
     * @return Macro
     */
    public static function __instance(string $namespace): self
    {
        if (!isset(static::$instances[$namespace])) {
            static::$instances[$namespace] = new static($namespace);
        }

        return static::$instances[$namespace];
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return Macro
     */
    public static function __callStatic(string $name, array $arguments): self
    {
        $namespace = array_shift($arguments);

        static::$macros[$namespace][$name] = reset($arguments);

        return static::__instance($namespace);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     */
    public function __call(string $name, array $arguments)
    {
        if ('new' === $name) {
            $method = array_shift($arguments);

            static::$macros[$this->namespace][$method] = reset($arguments);

            return $this;
        }

        if (isset(static::$macros[$this->namespace])) {
            $macro = static::$macros[$this->namespace][$name] ?? null;

            if (null !== $macro) {
                if (is_callable($macro)) {
                    return $macro(...$arguments);
                }

                return $macro;
            }
        }

        return null;
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
}
