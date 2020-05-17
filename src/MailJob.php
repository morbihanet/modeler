<?php

namespace Morbihanet\Modeler;

use Exception;
use Illuminate\Bus\Queueable;
use Swift_Mime_SimpleMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class MailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $message = null;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function handle()
    {
        try {
            $handler = curl_init(config('mail.remote.url'));

            $data = [
                'eml'   => $this->message,
                'key'   => config('mail.remote.key'),
            ];

            curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($handler, CURLOPT_POST, 1);
            curl_setopt($handler, CURLOPT_POSTFIELDS, $data);

            $result = curl_exec($handler);

            curl_close($handler);

            return 'OK' === $result ? 1 : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}