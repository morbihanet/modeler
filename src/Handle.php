<?php
namespace Morbihanet\Modeler;

use Closure;
use Illuminate\Queue\SerializableClosure;

class Handle extends SerializableClosure
{
    protected array $args = [];

    public function __construct(Closure $closure, ...$args)
    {
        $this->args = $args;

        parent::__construct($closure);
    }

    public function handle()
    {
        $callable = $this->closure;
        $callable(...$this->args);
    }
}
