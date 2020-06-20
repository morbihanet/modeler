<?php
namespace Morbihanet\Modeler;

class Schedule extends Model
{
    public static function boot()
    {
        static::$store = config('modeler.schedule_store', Store::class);
        parent::boot();
    }
}
