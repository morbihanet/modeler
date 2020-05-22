<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Collection;

class Collector
{
    protected ?Collection $collection = null;

    public function __construct($items = null)
    {
        $this->collection = collect();

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

    public function __call(string $name, array $arguments)
    {
        $value = $this->collection->{$name}(...$arguments);

        if ($value instanceof Collection) {
            $this->collection = $value;

            return $this;
        }

        return $value;
    }
}
