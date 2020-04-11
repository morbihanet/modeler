<?php

use Illuminate\Support\Str;
use Morbihanet\Modeler\Store;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\LiteStore;
use Morbihanet\Modeler\RedisStore;
use Morbihanet\Modeler\MemoryStore;

if (!function_exists('modeler')) {
    function modeler(string $model, ?string $store = null)
    {
        $store = $store ?? config('modeler.modeler_store', Store::class);

        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));
        $namespace = config('modeler.model_class', 'DB\\Models');

        $class = $namespace . '\\' . $model;

        if (class_exists($class)) {
            return new $class;
        }

        $code = 'namespace ' . $namespace . ';';

        if ($store === Store::class) {
            $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\Store {}';
        } else if ($store === RedisStore::class) {
            $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\RedisStore {}';
        } else if ($store === MemoryStore::class) {
            $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\MemoryStore {}';
        } else if ($store === FileStore::class) {
            $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\FileStore {}';
        } else if ($store === LiteStore::class) {
            $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\LiteStore {}';
        }

        eval($code);

        return new $class;
    }
}