<?php
namespace Morbihanet\Modeler;

use Illuminate\Notifications\RoutesNotifications;

trait Notifiable
{
    use RoutesNotifications;

    /**
     * @return Iterator
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable')->orderBy('created_at', 'desc');
    }

    public function readNotifications()
    {
        return $this->notifications()->whereNotNull('read_at');
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }
}