<?php

use Illuminate\Support\Str;
use Morbihanet\Modeler\Store;
use Morbihanet\Modeler\Valued;
use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\LiteStore;
use Morbihanet\Modeler\RedisStore;
use Morbihanet\Modeler\MemoryStore;

if (!function_exists('db_generator')) {
    function db_generator(string $model, string $namespace, string $store): Modeler
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (class_exists($class)) {
            return new $class;
        }

        $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Morbihanet\\Modeler\\Modeler {';

        if ($store === Store::class) {
            $code .= 'protected static string $store = \\Morbihanet\\Modeler\\Store::class;}';
        } else if ($store === RedisStore::class) {
            $code .= 'protected static string $store = \\Morbihanet\\Modeler\\RedisStore::class;}';
        } else if ($store === MemoryStore::class) {
            $code .= 'protected static string $store = \\Morbihanet\\Modeler\\MemoryStore::class;}';
        } else if ($store === FileStore::class) {
            $code .= 'protected static string $store = \\Morbihanet\\Modeler\\FileStore::class;}';
        } else if ($store === LiteStore::class) {
            $code .= 'protected static string $store = \\Morbihanet\\Modeler\\LiteStore::class;}';
        }

        eval($code);

        return new $class;
    }
}

if (!function_exists('db_store')) {
    function db_store(string $model, string $namespace = 'DB\\Models', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, Store::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('redis_store')) {
    function redis_store(string $model, string $namespace = 'Redis\\Models', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, RedisStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('memory_store')) {
    function memory_store(string $model, string $namespace = 'Memory\\Models', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, MemoryStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('file_store')) {
    function file_store(string $model, string $namespace = 'File\\Models', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, FileStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('lite_store')) {
    function lite_store(string $model, string $namespace = 'Lite\\Models', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, LiteStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('valued')) {
    function valued(string $model, string $namespace = 'Valued\\Models'): Valued
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (class_exists($class)) {
            return new $class;
        }

        $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Morbihanet\\Modeler\\Valued {}';

        eval($code);

        return new $class;
    }
}

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