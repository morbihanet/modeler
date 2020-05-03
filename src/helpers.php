<?php

use Illuminate\Support\Str;
use Morbihanet\Modeler\Item;
use Illuminate\Http\Request;
use Morbihanet\Modeler\Store;
use Morbihanet\Modeler\Model;
use Morbihanet\Modeler\Valued;
use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\LiteStore;
use Morbihanet\Modeler\RedisStore;
use Morbihanet\Modeler\MemoryStore;
use Illuminate\Support\Facades\Route;

if (!function_exists('model_generator')) {
    function model_generator(string $model, array $attributes, string $namespace, string $store): Model
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (class_exists($class)) {
            return new $class($attributes);
        }

        $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Morbihanet\\Modeler\\Model {';

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

        return new $class($attributes);
    }
}

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

if (!function_exists('db_model')) {
    function db_model(
        string $model,
        array $attributes = [],
        string $namespace = 'DB\\Models',
        bool $authenticable = false
    ): Model {
        return model_generator($model, $attributes, $namespace, Store::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('redis_model')) {
    function redis_model(
        string $model,
        array $attributes = [],
        string $namespace = 'Redis\\Models',
        bool $authenticable = false
    ): Model {
        return model_generator('redis_' . $model, $attributes, $namespace, RedisStore::class)
            ->setAuthenticable($authenticable);
    }
}

if (!function_exists('memory_model')) {
    function memory_model(
        string $model,
        array $attributes = [],
        string $namespace = 'Memory\\Models',
        bool $authenticable = false
    ): Model {
        return model_generator('memory_' . $model, $attributes, $namespace, MemoryStore::class)
            ->setAuthenticable($authenticable);
    }
}

if (!function_exists('file_model')) {
    function file_model(
        string $model,
        array $attributes = [],
        string $namespace = 'File\\Models',
        bool $authenticable = false
    ): Model {
        return model_generator('file_' . $model, $attributes, $namespace, FileStore::class)
            ->setAuthenticable($authenticable);
    }
}

if (!function_exists('lite_model')) {
    function lite_model(
        string $model,
        array $attributes = [],
        string $namespace = 'Lite\\Models',
        bool $authenticable = false
    ): Model {
        return model_generator('lite_' . $model, $attributes, $namespace, LiteStore::class)
            ->setAuthenticable($authenticable);
    }
}

if (!function_exists('db_store')) {
    function db_store(string $model, string $namespace = 'DB\\Repositories', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, Store::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('lite_model')) {
    function lite_model(string $model, string $namespace = 'Lite\\Models', bool $authenticable = false): Model
    {
        return model_generator($model, $namespace, LiteStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('redis_store')) {
    function redis_store(string $model, string $namespace = 'Redis\\Repositories', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, RedisStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('memory_store')) {
    function memory_store(string $model, string $namespace = 'Memory\\Repositories', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, MemoryStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('file_store')) {
    function file_store(string $model, string $namespace = 'File\\Repositories', bool $authenticable = false): Modeler
    {
        return db_generator($model, $namespace, FileStore::class)->setAuthenticable($authenticable);
    }
}

if (!function_exists('lite_store')) {
    function lite_store(string $model, string $namespace = 'Lite\\Repositories', bool $authenticable = false): Modeler
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

    if (!function_exists('model_rest')) {
        function model_rest(Item $item, string $prefix = 'api')
        {
            $class = get_class($item);
            $parts = explode('\\', $class);
            $model = Str::camel(Str::lower(array_pop($parts)) . '_resource');

            $name = Str::plural($model);

            $db = get_class($item->getDb());

            Route::group(['prefix' => $prefix], function () use ($name, $db) {
                Route::get($name, function() use ($db) {
                    return response()->json($db::all()->toArray());
                });

                Route::get($name . '/{id}', function($id) use ($db) {
                    if (!$item = $db::find($id)) {
                        return response()->json([
                            'error' => 'Resource not found'
                        ], 404);
                    }

                    return response()->json($item->toArray());
                });

                Route::post($name, function(Request $request) use ($db) {
                    return response()->json($db::create($request->all()));
                });

                Route::put($name . '/{id}', function(Request $request, $id) use ($db) {
                    if (!$item = $db::find($id)) {
                        return response()->json([
                            'error' => 'Resource not found'
                        ], 404);
                    }

                    $item->update($request->all());

                    return response()->json($item->toArray());
                });

                Route::delete($name . '/{id}', function($id) use ($db) {
                    if (!$item = $db::find($id)) {
                        return response()->json([
                            'error' => 'Resource not found'
                        ], 404);
                    }

                    $item->delete();

                    return response()->json([], 204);
                });
            });
        }
    }

    if (!function_exists('item_resource')) {
        function item_resource(Item $item)
        {
            $class = get_class($item);
            $parts = explode('\\', $class);
            $model = ucfirst(Str::camel(Str::lower(array_pop($parts)) . '_resource'));
            $namespace = implode('\\', $parts);

            $class = $namespace . '\\' . $model;

            if (class_exists($class)) {
                return new $class($item);
            }

            $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Illuminate\\Http\\Resources\\Json\\JsonResource {
            public function __toString()
            {
                return $this->resource->toJson();
            }
            }';

            eval($code);

            return new $class($item);
        }
    }
}