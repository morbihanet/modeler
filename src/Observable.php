<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

trait Observable
{
    /**
     * @param string $class
     * @return Db
     */
    public function observe(string $class): self
    {
        $observers = Core::get('core.events.observers', []);
        $observers[get_called_class()] = $class;
        Core::set('core.events.observers', $observers);
    }

    public function fire(string $name, $concern = null, bool $return = false)
    {
        $methods = get_class_methods($this);
        $method  = Str::camel('on_' . $name);

        if (in_array($method, $methods)) {
            $result = $this->{$method}($concern);

            if ($return) {
                return $result;
            }
        } else {
            $observers = Core::get('itdb.observers', []);
            $self = get_called_class();

            $observer = Arr::get($observers, $self, null);

            if (null !== $observer) {
                $methods = get_class_methods($observer);

                if (in_array($name, $methods)) {
                    $result = (new $observer)->{$name}($concern);

                    if ($return) {
                        return $result;
                    }
                }
            }
        }

        return $concern;
    }
}