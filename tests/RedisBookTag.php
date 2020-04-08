<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\RedisStore;

class RedisBookTag extends Modeler
{
    protected static string $store = RedisStore::class;
}
