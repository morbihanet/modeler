<?php
namespace Morbihanet\Modeler;

use Illuminate\Database\Seeder as DBSeed;

class Seeder extends DBSeed
{
    public function run()
    {
        Event::fire('db.seed', $this);
    }
}