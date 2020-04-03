<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\MemoryStore;

class MemoryAuthor extends Modeler
{
    protected static $store = MemoryStore::class;
}
