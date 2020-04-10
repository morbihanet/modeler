<?php

use App\Models\User;
use Morbihanet\Modeler\Store;


return [
    'model_class' => 'App\\Repositories',
    'item_class' => 'App\\Entities',
    'cache_ttl' => 24 * 3600,
    'file_dir' => storage_path("dbf"),
    'user_model' => User::class,
    'schedule_store' => Store::class,
    'notification_store' => Store::class,
    'bearer_store' => Store::class,
    'scheduler_route' => '/modeler/scheduler/cron',
];