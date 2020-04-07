<?php
namespace Morbihanet\Modeler;

class Memory extends Warehouse
{
    public function __construct(array $attributes = [])
    {
        Database::memory();
        $this->connection = 'db_memory';

        parent::__construct($attributes);
    }

    public static function boot()
    {
        Database::memory();
        parent::boot();
    }
}
