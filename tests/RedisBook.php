<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\RedisStore;

class RedisBook extends Modeler
{
    protected static string $store = RedisStore::class;
}
