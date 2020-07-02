<?php
namespace Morbihanet\Modeler;

use ArrayAccess;
use Illuminate\Support\Traits\Macroable;

class Di implements ArrayAccess
{
    use Macroable;

    protected static array $values = [
        'singletons' => [],
        'instances' => [],
    ];

    protected static ?Di $instance = null;

    public static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function offsetExists($offset): bool
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

    public function get(string $name, $default = null)
    {
        return Core::get($name, $default);
    }

    public function getShared(string $name, $default = null)
    {
        return Core::get('singleton_' . $name, $default);
    }

    public function has(string $name): bool
    {
        $singleton = Core::has('singleton_' . $name);
        $instance = Core::has('instance_' . $name);

        return $singleton || $instance;
    }

    public function hasSingleton(string $name): bool
    {
        return Core::has('singleton_' . $name);
    }

    public function hasInstance(string $name): bool
    {
        return Core::has('instance_' . $name);
    }

    public function remove(string $name): bool
    {
        $singleton = Core::has('singleton_' . $name);
        $instance = Core::has('instance_' . $name);
        $status = $singleton || $instance;

        if ($status) {
            if ($singleton) {
                Core::delete('singleton_' . $name);
            }

            if ($instance) {
                Core::delete('instance_' . $name);
            }
        }

        return $status;
    }

    public static function reset(): int
    {
        $singletons = static::$values['singletons'];
        $instances = static::$values['instances'];

        $i = 0;

        foreach ($singletons as $name) {
            Core::delete('singleton_' . $name);
            ++$i;
        }

        foreach ($instances as $name) {
            Core::delete('singleton_' . $name);
            ++$i;
        }

        return $i;
    }

    public function set(string $name, $definition, bool $shared = false): self
    {
        if (true === $shared) {
            return $this->setShared($name, $definition);
        }

        Core::instance($name, $definition);

        if (!in_array($name, static::$values['instances'])) {
            static::$values['instances'][] = $name;
        }

        return $this;
    }

    public function setShared(string $name, $definition): self
    {
        Core::singleton($name, $definition);

        if (!in_array($name, static::$values['singletons'])) {
            static::$values['singletons'][] = $name;
        }

        return $this;
    }
}
