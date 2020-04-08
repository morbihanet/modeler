<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\FileStore;

class FileBook extends Modeler
{
    protected static string $store = FileStore::class;
}
