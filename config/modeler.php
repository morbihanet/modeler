<?php
return [
    'model_class' => 'App\\Repositories',
    'item_class' => 'App\\Entities',
    'cache_ttl' => 24 * 3600,
    'file_dir' => storage_path("dbf"),
    'user_model' => \App\Models\User::class,
];
