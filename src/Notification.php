<?php

namespace Morbihanet\Modeler;

class Notification extends Model
{
    public static function boot()
    {
        static::$store = config('modeler.notification_store', Store::class);
        parent::boot();
    }
}
