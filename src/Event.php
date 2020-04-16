<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class Event
{
    /**
     * @param string $name
     * @param callable $callable
     */
    public static function add(string $name, callable $callable)
    {
        $events = Core::get('core.events', []);
        $events[$name] = $callable;
        Core::set('core.events', $events);
    }

    public static function fire(string $name, $concern = null, bool $return = false)
    {
        $events = Core::get('core.events', []);
        $callable = $events[$name] ?? null;

        if (is_callable($callable)) {
            $result = $callable($concern);

            if ($return) {
                return $result;
            }
        }

        return $concern;
    }
}