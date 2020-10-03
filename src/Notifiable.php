<?php
namespace Morbihanet\Modeler;

use Illuminate\Notifications\RoutesNotifications;

trait Notifiable
{
    use RoutesNotifications;

    public function notify(array $data)
    {
        app(Notifier::class)->send($this, $data);
    }

    public function notifyNow(array $data)
    {
        app(Notifier::class)->sendNow($this, $data);
    }

    /**
     * @return Iterator
     */
    public function notifications()
    {
        return Notification::where('notifiable_id', $this->getId())->where('notifiable', get_class($this));
    }

    public function readNotifications()
    {
        return $this->notifications()->whereNotNull('read_at');
    }

    public function readNotification(Item $notification)
    {
        $notification->read_at = time();

        return $notification->save();
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }
}
