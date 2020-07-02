<?php

namespace Morbihanet\Modeler;

class Proxy
{
    /** @var Iterator */
    protected $iterator;

    /** @var string */
    protected $method;

    /**
     * @param Iterator $iterator
     * @param $method
     */
    public function __construct(Iterator $iterator, string $method)
    {
        $this->method = $method;
        $this->iterator = $iterator;
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $value = $this->iterator->{$this->method}();

        return value(function () use ($value, $key) {
            return is_array($value) ? $value[$key] : $value->{$key};
        });
    }

    /**
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->iterator->{$this->method}(function ($value) use ($method, $parameters) {
            return $value->{$method}(...$parameters);
        });
    }
}
