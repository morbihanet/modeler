<?php

namespace Morbihanet\Modeler\Test;

use Faker\Generator as Faker;
use Morbihanet\Modeler\Modeler;

class Book extends Modeler
{
    public static function seeder(Faker $faker): array
    {
        return [
            'name' => $faker->sentence
        ];
    }
}
