<?php
namespace Morbihanet\Modeler\Test;

class Swappable
{
    private function test()
    {
        return 'baz';
    }

    private function withParams(int $a, int $b)
    {
        return $a * $b;
    }
}
