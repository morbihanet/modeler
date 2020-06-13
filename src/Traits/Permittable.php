<?php
namespace Morbihanet\Modeler\Traits;

use Morbihanet\Modeler\Session;
use Morbihanet\Modeler\Permitter;
use Morbihanet\Modeler\Interfaces\Monitor;

trait Permittable
{
    protected static $permitterUser;

    public static function getPermitter()
    {
        return Permitter::getInstance(get_called_class());
    }

    public static function addMonitor(Monitor $monitor): Permitter
    {
        return static::getPermitter()->addMonitor($monitor);
    }

    public static function removeMonitor($monitor): Permitter
    {
        return static::getPermitter()->removeMonitor($monitor);
    }

    public static function checkPermission(string $permission, $concern = null): bool
    {
        return static::getPermitter()->check(static::getPermitterUser(), $permission, $concern);
    }

    public static function getPermitterUser()
    {
        return static::$permitterUser ?? Session::getInstance()->user();
    }

    public static function setPermitterUser($permitterUser): void
    {
        static::$permitterUser = $permitterUser;
    }
}
