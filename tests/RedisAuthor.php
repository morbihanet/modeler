<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\RedisStore;

class RedisAuthor extends Modeler
{
    protected static $store = RedisStore::class;
}
