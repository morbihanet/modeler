<?php
namespace Morbihanet\Modeler;

use Morbihanet\Modeler\Interfaces\Monitor;

class MonitorAdmin implements Monitor
{
    public function able($user, string $permission, $concern = null): bool
    {
        return $user->isAdmin();
    }

    public function decide($user, string $permission, $concern = null): bool
    {
        return $user->isAdmin();
    }
}
