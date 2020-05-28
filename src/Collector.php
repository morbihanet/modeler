<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Collector
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    protected ?Collection $collection = null;

    public function __construct($items = null)
    {
        $this->collection = Collection::make();

        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public function add($item): self
    {
        $this->collection = $this->collection->add($item);

        return $this;
    }

    public function remove($id, string $key = 'id'): bool
    {
        $count = $this->collection->count();

        $this->collection = $this->collection->filter(function ($item) use ($id, $key) {
            if (isset($item[$key])) {
                return $item[$key] !== $id;
            }

            return false;
        });

        return $count < $this->collection->count();
    }

    /**
     * @return mixed|null
     */
    public function find($id, string $key = 'id')
    {
        return $this->collection->where($key, $id)->first();
    }

    /**
     * @return mixed|Collector
     */
    public function __call($name, $arguments)
    {
        if (static::hasMacro($name)) {
            return $this->macroCall($name, $arguments);
        }

        $value = $this->collection->{$name}(...$arguments);

        if ($value instanceof Collection) {
            $this->collection = $value;

            return $this;
        }

        return $value;
    }
}
