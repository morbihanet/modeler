<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Item;
use Morbihanet\Modeler\Model;

class Product extends Model
{
    public function scopeTest($query, $test)
    {
        return $query->whereName($test);
    }

    public function dummy(Item $item, int $x)
    {
        return $item->getId() + $x;
    }
}
