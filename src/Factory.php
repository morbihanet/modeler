<?php
namespace Morbihanet\Modeler;

use Faker\Generator as Faker;
use Illuminate\Support\Fluent;
/**
 * @method Factory times(int $t = 1)
 * @method Db|array make(array $attrs = [], bool $toCollection = false, ?Faker $faker = null)
 * @method Db|array create(array $attrs = [], bool $toCollection = false, ?Faker $faker = null)
 */
class Factory extends Fluent
{

}