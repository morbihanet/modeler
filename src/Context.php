<?php

namespace Morbihanet\Modeler;

class Context
{
    /**
     * @var array
     */
    protected static array $context = [];

    /**
     * @param array $context
     */
    public static function set(array $context): void
    {
        static::$context = $context;
    }

    public static function get(): array
    {
        return static::$context;
    }

    /**
     * @param array $contextToMerge
     */
    public static function merge(array $contextToMerge): void
    {
        static::$context = array_merge(self::$context, $contextToMerge);
    }
}
