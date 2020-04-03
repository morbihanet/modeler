<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\FileStore;

class FileBookTag extends Modeler
{
    protected static $store = FileStore::class;
}
