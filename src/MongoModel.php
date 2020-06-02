<?php
namespace Morbihanet\Modeler;

class MongoModel extends \Jenssegers\Mongodb\Eloquent\Model
{
    protected $hidden = [];
    protected $guarded = [];
    protected $connection = 'mongodb';
}
