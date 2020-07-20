<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin  \Morbihanet\Modeler\Belt
 */

class Router extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Belt::getInstance();
    }
}
