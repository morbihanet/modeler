<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Facades\Facade;

class Router extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Belt::getInstance();
    }
}
