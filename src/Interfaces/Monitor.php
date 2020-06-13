<?php
namespace Morbihanet\Modeler\Interfaces;

interface Monitor
{
    public function able($user, string $permission, $concern = null): bool;
    public function decide($user, string $permission, $concern = null): bool;
}
