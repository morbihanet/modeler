<?php
namespace Morbihanet\Modeler;

class Resource
{
    protected ?Item $resource = null;

    public function __construct(?Item $resource = null)
    {
        $this->resource = $resource;
    }

    public function toArray()
    {
        if (is_null($this->resource)) {
            return [];
        }

        return is_array($this->resource)
            ? $this->resource
            : $this->resource->toArray();
    }
}