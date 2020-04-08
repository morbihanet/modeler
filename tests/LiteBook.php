<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\LiteStore;

class LiteBook extends Modeler
{
    protected static string $store = LiteStore::class;
}
