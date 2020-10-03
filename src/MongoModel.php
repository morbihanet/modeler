<?php
namespace Morbihanet\Modeler;

use Jenssegers\Mongodb\Eloquent\Model;

class MongoModel extends Model
{
    protected $hidden = [];
    protected $guarded = [];
    protected $connection = 'mongodb';
}
