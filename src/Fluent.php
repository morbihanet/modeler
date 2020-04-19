<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

class Fluent extends \Illuminate\Support\Fluent
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    public function get($key, $default = null)
    {
        $value = $this->attributes[$key] ?? $default;

        return value($value);
    }

    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (substr($method, 0, 3) === 'set' && strlen($method) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 3)));
            $field              = Str::lower($uncamelizeMethod);

            $v = array_shift($parameters);

            $this->attributes[$field] = $v;

            return $this;
        }

        if (substr($method, 0, 3) === 'get' && strlen($method) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 3)));
            $field              = Str::lower($uncamelizeMethod);

            $d = array_shift($arguments);

            return $this->get($field, $d);
        }

        if (substr($method, 0, 3) === 'has' && strlen($method) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 3)));
            $field              = Str::lower($uncamelizeMethod);

            return $this->__isset($field);
        }

        if (substr($method, 0, 3) === 'del' && strlen($method) > 3) {
            $uncamelizeMethod   = Core::uncamelize(lcfirst(substr($method, 3)));
            $field              = Str::lower($uncamelizeMethod);

            $status = $this->__isset($field);
            unset($this->attributes[$field]);

            return $status;
        }

        $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;

        return $this;
    }
}