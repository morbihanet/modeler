<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\MemoryStore;

class MemoryBookTag extends Modeler
{
    protected static string $store = MemoryStore::class;
}
