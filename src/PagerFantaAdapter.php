<?php
namespace Morbihanet\Modeler;

use Pagerfanta\Adapter\CallbackAdapter;

class PagerFantaAdapter extends CallbackAdapter
{
    public function __construct(Iterator $iterator)
    {
        parent::__construct(function () use ($iterator) {
            return $iterator->count();
        }, function ($offset, $length) use ($iterator) {
            return $iterator->slice($offset, $length);
        });
    }
}
