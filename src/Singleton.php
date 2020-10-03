<?php

namespace Morbihanet\Modeler;

class Singleton
{
    private static $__instance;

    protected function __construct(...$args) {}

    public static function getInstance(...$args): self
    {
        if (null === static::$__instance) {
            static::$__instance = new static(...$args);
        }

        return static::$__instance;
    }
}
