<?php

namespace Morbihanet\Modeler\Test;

use Illuminate\Support\Str;
use Morbihanet\Modeler\Traits\Resolvable;

class Resolver
{
    use Resolvable;

    public static function boot()
    {
        static::setResolver(function () {
            return new Str;
        });
    }
}
