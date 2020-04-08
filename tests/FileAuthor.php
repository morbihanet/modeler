<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Modeler;
use Morbihanet\Modeler\FileStore;

class FileAuthor extends Modeler
{
    protected static string $store = FileStore::class;
}
