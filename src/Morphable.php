<?php
namespace Morbihanet\Modeler;

trait Morphable
{
    public function morphOne(string $morphClass, string $morphName)
    {
        return $morphClass::where($morphName, get_class(Core::getDb($this)))
            ->where($morphName . '_id', $this['id'])
            ->first();
    }

    public function morphMany(string $morphClass, string $morphName)
    {
        return $morphClass::where($morphName, get_class(Core::getDb($this)))
            ->where($morphName . '_id', $this['id'])
            ->cursor();
    }
}
