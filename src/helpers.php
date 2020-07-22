<?php

use Illuminate\Support\Str;
use Morbihanet\Modeler\Dyn;
use Morbihanet\Modeler\Item;
use Illuminate\Http\Request;
use Morbihanet\Modeler\Core;
use Morbihanet\Modeler\Store;
use Morbihanet\Modeler\Model;
use Morbihanet\Modeler\Valued;
use Morbihanet\Modeler\Record;
use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\Context;
use Morbihanet\Modeler\Accessor;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\LiteStore;
use Morbihanet\Modeler\RedisStore;
use Morbihanet\Modeler\MongoStore;
use Morbihanet\Modeler\MemoryStore;
use Morbihanet\Modeler\Data\Session;
use Illuminate\Support\Facades\Route;

if (!function_exists('record')) {
    function record($data)
    {
        return new Record((array) $data);
    }
}

if (!function_exists('resolver')) {
    function resolver(string $name, $resolver = null): Accessor
    {
        $namespace = 'App\\Resolvers';

        $name = ucfirst(Str::camel(str_replace('.', '\\_', $name)));

        $class = $namespace . '\\' . $name;

        if (!class_exists($class)) {
            $code = 'namespace ' . $namespace . '; class ' . $name . ' extends \\Morbihanet\\Modeler\\Accessor {}';

            eval($code);
        }

        if (null !== $resolver) {
            $class::resolver($resolver);
        }

        return new $class;
    }
}

if (!function_exists('make_with')) {
    function make_with(string $class, ...$params)
    {
        static $made = [];

        if (!isset($made[$class])) {
            $made[$class] = app()->make($class, $params);
        }

        return $made[$class];
    }
}

if (!function_exists('model_generator')) {
    function model_generator(string $model, array $attributes, string $namespace, string $store): Model
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (!class_exists($class)) {
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
            } else if ($store === MongoStore::class) {
                $code .= 'protected static string $store = \\Morbihanet\\Modeler\\MongoStore::class;}';
            }

            eval($code);
        }

        return Core::set('last_datum', new $class($attributes));
    }
}

if (!function_exists('db_generator')) {
    function db_generator(string $model, string $namespace, string $store): Modeler
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (!class_exists($class)) {
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
            } else if ($store === MongoStore::class) {
                $code .= 'protected static string $store = \\Morbihanet\\Modeler\\MongoStore::class;}';
            }

            eval($code);
        }

        return Core::set('last_datum', new $class);
    }
}

if (!function_exists('context')) {
    function context(string $context = 'core', array $attributes = []): Context
    {
        return Context::getInstance($context, $attributes);
    }
}

if (!function_exists('redis_session')) {
    function redis_session(string $namespace = 'web')
    {
        $data = redis_data('session_' . $namespace);

        return new Session($data);
    }
}

if (!function_exists('redis_data')) {
    function redis_data(string $model, array $attributes = []): \Morbihanet\Modeler\Data\Redis
    {
        $namespace = config('modeler.data_class', '\\App\\Data') . '\\Redis';

        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (!class_exists($class)) {
            $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Morbihanet\\Modeler\\Data\\Redis {}';

            eval($code);
        }

        return new $class($attributes);
    }
}

if (!function_exists('app_config')) {
    function app_config($context = null)
    {
        return Record::make(config($context));
    }
}

if (!function_exists('doc')) {
    function doc(
        string $model,
        ?string $database = null,
        array $attributes = [],
        ?string $namespace = null,
        bool $authenticable = false
    ): Model {
        return is_mongo(datum(
            $model,
            $database ?? 'doc',
            $attributes,
            $namespace ?? config('modeler.doc_class', 'App\\Doc\\Models'),
            $authenticable,
            'MongoStore'
        ), $model);
    }

    function is_booting($class, ?bool $status = null)
    {
        static $is_booting = [];

        if (is_bool($status)) {
            $is_booting[$class] = $status;

            return false;
        }

        if (Str::startsWith($class, config('modeler.doc_class', 'App\\Doc\\Models'))) {
            return true;
        }

        return $is_booting[$class] ?? false;
    }

    /**
     * @return Model|string|null
     */
    function is_mongo($concern, ?string $mapped = null)
    {
        static $mongod = [];

        if (is_string($concern) && empty($mapped)) {
            return $mongod[$concern] ?? null;
        }

        if (is_string($concern)) {
            $concern = new $concern;
        }

        if (!isset($mongod[$index = get_class($concern)])) {
            $mongod[$index] = $mapped;
        }

        return $concern;
    }
}

if (!function_exists('datum')) {
    function datum(
        string $model,
        ?string $database = null,
        array $attributes = [],
        ?string $namespace = null,
        bool $authenticable = false,
        string $store = 'Store'
    ): Model {
        if (is_array($database)) {
            $attributes = $database;

            $database = null;
        }

        if (!empty($database)) {
            $model = $database . '_' . $model;
        }

        $namespace = $namespace ?? config('modeler.datum_class');

        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (!class_exists($class)) {
            $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Morbihanet\\Modeler\\Model {';
            $code .= 'protected static string $store = \\Morbihanet\\Modeler\\'.$store.'::class;}';

            eval($code);
        }

        $object = (new $class)->setAuthenticable($authenticable);

        if (!empty($attributes)) {
            $object->fillAndSave($attributes);
        }

        return Core::set('last_datum', $object);
    }

    function item_table(Item $item): string
    {
        return Str::lower(Core::uncamelize(class_basename($item)));
    }

    function get_datum(string $class)
    {
        if (!class_exists($class)) {
            $parts = explode('\\', $class);
            $last = Core::uncamelize(array_pop($parts));

            if ($class === get_class($db = datum($last))) {
                return $db;
            }

            return null;
        }

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
    function valued(string $model, string $namespace = 'App\\Valued\\Models'): Valued
    {
        $model = ucfirst(Str::camel(str_replace('.', '\\_', $model)));

        $class = $namespace . '\\' . $model;

        if (!class_exists($class)) {
            $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Morbihanet\\Modeler\\Valued {}';

            eval($code);
        }

        return new $class;
    }
}

if (!function_exists('tooler')) {
    function tooler(string $name): Dyn
    {
        $name = ucfirst(Str::camel(str_replace('.', '\\_', $name)));
        $namespace = 'Morbihanet\\Tools';
        $class = $namespace . '\\' . $name;

        if (!class_exists($class)) {
            $code = 'namespace ' . $namespace . ';';
            $code .= 'class ' . $name . ' extends \\Morbihanet\\Modeler\\Dyn {}';

            eval($code);
        }

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

        if (!class_exists($class)) {
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
            } else if ($store === MongoStore::class) {
                $code .= 'class ' . $model . ' extends \\Morbihanet\\Modeler\\MongoStore {}';
            }

            eval($code);
        }

        return new $class;
    }

    if (!function_exists('getNamespaceByStore')) {
        function getNamespaceByStore(string $store)
        {
            $store = str_replace('Store', '\\', class_basename($store));

            if ($store === '\\') {
                $store = 'Db';
            }

            return rtrim('App\\Entities\\' . $store, '\\');
        }
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

            if (!class_exists($class)) {
                $code = 'namespace ' . $namespace . '; class ' . $model . ' extends \\Illuminate\\Http\\Resources\\Json\\JsonResource {
            public function __toString()
            {
                return $this->resource->toJson();
            }
            }';

                eval($code);
            }

            return new $class($item);
        }
    }
}
