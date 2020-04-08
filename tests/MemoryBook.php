<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\MemoryStore;

class MemoryBook extends Modeler
{
    protected static string $store = MemoryStore::class;
}
