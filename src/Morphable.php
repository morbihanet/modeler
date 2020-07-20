<?php
namespace Morbihanet\Modeler;

trait Morphable
{
    public function morphOne(string $morphClass, string $morphName): Item
    {
        return $morphClass::where($morphName, get_class($this))
            ->where($morphName . '_id', $this['id'])
            ->first();
    }

    public function morphMany(string $morphClass, string $morphName): Iterator
    {
        return $morphClass::where($morphName, get_class($this))
            ->where($morphName . '_id', $this['id']);
    }
}
