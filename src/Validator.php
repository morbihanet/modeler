<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator as Father;

class Validator extends Father
{
    public function getErrors(): MessageBag
    {
        return $this->messages();
    }

    public function hasSucceeded(): bool
    {
        return $this->passes();
    }

    public function hasFailed(): bool
    {
        return !$this->passes();
    }

    public function validate(): array
    {
        return $this->validated();
    }

    public function validated(): array
    {
        $results = [];

        $missingValue = Str::random(10);

        foreach (array_keys($this->getRules()) as $key) {
            $value = data_get($this->getData(), $key, $missingValue);

            if ($value !== $missingValue) {
                Arr::set($results, $key, $value);
            }
        }

        return $results;
    }
}