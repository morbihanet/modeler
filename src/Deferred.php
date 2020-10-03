<?php

namespace Morbihanet\Modeler;

class Deferred
{
    protected $resolveData;
    protected $otherwiseData;

    public function resolve($data): self
    {
        $this->resolveData = $data;

        return $this;
    }

    public function reject($data): self
    {
        $this->otherwiseData = $data;

        return $this;
    }

    public function promise(): Promise
    {
        return new Promise(value($this->resolveData), value($this->otherwiseData));
    }
}

/**
 * function test($number)
{
    $deferred = new Deferred();

    if ($number > 2) {
        $deferred->resolve('success');
    } else {
        $deferred->reject('error');
    }

    return $deferred->promise();
}

test(1)->then(function ($data) {
    echo $data;
})->otherwise(function ($error) {
    echo $error;
});
 */
