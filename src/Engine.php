<?php
namespace Morbihanet\Modeler;

class Engine
{
    protected $engine;

    public function engine($engine = null)
    {
        if (null !== $engine) {
            $this->engine = $engine;
        }

        return value($this->engine);
    }

    public function __call(string $name, array $arguments)
    {
        if ($this->engine) {
            return $this->engine->{$name}(...$arguments);
        }

        return null;
    }
}