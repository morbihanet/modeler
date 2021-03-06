<?php

use App\Models\User;
use Morbihanet\Modeler\Store;

return [
    'data_class'            => 'App\\Data',
    'datum_class'           => 'App\\Datum\\Models',
    'model_class'           => 'App\\Repositories',
    'item_class'            => 'App\\Entities',
    'cache_ttl'             => 24 * 3600,
    'file_dir'              => storage_path('dbf'),
    'user_model'            => User::class,
    'schedule_store'        => Store::class,
    'notification_store'    => Store::class,
    'bearer_store'          => Store::class,
    'modeler_store'         => Store::class,
    'scheduler_route'       => '/modeler/scheduler/cron',
    'api_route'             => '/xapi',
    'cookie_name'           => 'app_bearer',
    'typesense'             => [
        'api_key' => 'morbihanet',
        'nodes' => [
            [
                'host' => 'typesense',
                'port' => '8108',
                'protocol' => 'http',
            ],
        ],
        'connection_timeout_seconds' => 2,
    ],
];
