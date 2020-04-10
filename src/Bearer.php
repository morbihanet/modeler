<?php

namespace Morbihanet\Modeler;

class Bearer extends Modeler
{
    public static function boot()
    {
        static::$store = config('modeler.bearer_store', Store::class);
        parent::boot();
    }
}