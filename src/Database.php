<?php

namespace Morbihanet\Modeler;

use PDO;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    protected static $memory = null;

    /**
     * @var array
     */
    protected static $baseParams = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ];

    /**
     * @param array $params
     * @return Capsule
     */
    public static function connect(array $params, string $name)
    {
        $capsule = new Capsule($app = app());

        $params = array_merge(static::$baseParams, $params);
        $capsule->addConnection($params, $name);
        $capsule->setFetchMode(PDO::FETCH_ASSOC);
        $capsule->setEventDispatcher(new Dispatcher($app));
        $capsule->bootEloquent();

        return $capsule;
    }

    /**
     * @return Capsule
     */
    public static function memory()
    {
        if (null !== static::$memory) {
            return static::$memory;
        }

        $capsule = new Capsule($app = app());

        $params = array_merge(static::$baseParams, [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $capsule->addConnection($params, 'db_memory');
        $capsule->setFetchMode(PDO::FETCH_ASSOC);
        $capsule->setAsGlobal();
        $capsule->setEventDispatcher(new Dispatcher($app));
        $capsule->bootEloquent();

        app('config')->set('database.connections.db_memory', [
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'prefix'    => '',
        ]);

        $capsule->getConnection('db_memory')->getPdo()->exec("create table kv(
            k text not null primary key,
            v text null,
            e integer default '0' not null,
            called_at integer default '0' not null
        )");

        return static::$memory = $capsule;
    }
}
