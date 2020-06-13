<?php
namespace Morbihanet\Modeler;

use Morbihanet\Modeler\Interfaces\Monitor;

class MonitorLazy implements Monitor
{
    /**
     * @var callable
     */
    protected $able;

    /**
     * @var callable
     */
    protected $decide;

    public function __construct(callable $able, callable $decide)
    {
        $this->able = $able;
        $this->decide = $decide;
    }

    public function able($user, string $permission, $concern = null): bool
    {
        $able = $this->able;

        return $able($user, $permission, $concern);
    }

    public function decide($user, string $permission, $concern = null): bool
    {
        $decide = $this->decide;

        return $decide($user, $permission, $concern);
    }
}
