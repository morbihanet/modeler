<?php

namespace Morbihanet\Modeler;

class Promise
{
    protected $resolveData;
    protected $otherwiseData;
    protected $prevThenResult;

    public function __construct($resolveData, $otherwiseData)
    {
        $this->resolveData = $resolveData;
        $this->otherwiseData = $otherwiseData;
    }

    public function then(callable $callback): self
    {
        if ($this->resolveData) {
            $result = $callback($this->prevThenResult ?? $this->resolveData);

            if ($result) {
                $this->prevThenResult = $result;
            }
        }

        return $this;
    }

    public function otherwise(callable $callback): self
    {
        if ($this->otherwiseData) {
            $callback($this->otherwiseData);
        }

        return $this;
    }

    public function catch(callable $callback): self
    {
        return $this->otherwise($callback);
    }
}
