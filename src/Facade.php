<?php

namespace Morbihanet\Modeler;

use stdClass;
use RuntimeException;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Facades\Facade as Master;

class Facade extends Master
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    protected static string $accessor = stdClass::class;

    public function __set(string $name, $value): void
    {
        $this->__getInstanceBounded()->{$name} = $value;
    }

    public function __get(string $name)
    {
        return $this->__getInstanceBounded()->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->__getInstanceBounded()->{$name});
    }

    public function __unset(string $name): bool
    {
        $status = $this->__isset($name);

        unset($this->__getInstanceBounded()->{$name});

        return $status;
    }

    protected static function getFacadeAccessor()
    {
        return static::$accessor;
    }

    public function __call($name, $arguments)
    {
        if (static::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $this->__getInstanceBounded()->$name(...$arguments);
    }

    protected function __getInstanceBounded()
    {
        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return Core::bind($instance);
    }
}