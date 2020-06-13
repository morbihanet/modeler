<?php
namespace Morbihanet\Modeler;

class ConsoleObserver
{
    public function __call($name, $arguments)
    {
        if (fnmatch('*ing', $name)) {
            Core::incr('console_queries_writing');
        } else {
            Core::incr('console_queries_reading');
        }
    }
}
