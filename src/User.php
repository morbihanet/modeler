<?php
namespace Morbihanet\Modeler;

class User extends Model
{
    public function isAdmin(Item $user): bool
    {
        return true === $user->is_admin;
    }
}
