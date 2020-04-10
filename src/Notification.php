<?php

namespace Morbihanet\Modeler;

class Notification extends Modeler
{
    public static function boot()
    {
        static::$store = config('modeler.notification_store', Store::class);
        parent::boot();
    }
}