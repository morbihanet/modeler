<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\MongoStore;

class MongoBook extends Modeler
{
    protected static string $store = MongoStore::class;
}
