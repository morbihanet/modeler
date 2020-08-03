<?php
namespace Morbihanet\Modeler;

use Illuminate\Contracts\Notifications\Dispatcher;

class Notifier implements Dispatcher
{
    /**
     * @param Item $notifiable
     * @param array $data
     */
    public function send($notifiable, $data)
    {
        $data['notifiable'] = get_class($notifiable);
        $data['notifiable_id'] = $notifiable->getId();

        Notification::create($data);
    }

    /**
     * @param Item $notifiable
     * @param array $data
     */
    public function sendNow($notifiable, $data)
    {
        $this->send($notifiable, $data);
    }
}
