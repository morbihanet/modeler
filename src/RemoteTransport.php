<?php

namespace Morbihanet\Modeler;

use Exception;
use Swift_Mime_SimpleMessage;
use Illuminate\Mail\Transport\Transport;

class RemoteTransport extends Transport
{
    protected $queued = false;

    public function __construct(bool $queued = false)
    {
        $this->queued = $queued;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        if (true === $this->queued) {
            MailJob::dispatch($message->toString());
        } else {
            $this->beforeSendPerformed($message);

            try {
                $handler = curl_init(config('mail.remote.url'));

                $data = [
                    'eml'   => $message->toString(),
                    'key'   => config('mail.remote.key'),
                ];

                curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($handler, CURLOPT_POST, 1);
                curl_setopt($handler, CURLOPT_POSTFIELDS, $data);

                $result = curl_exec($handler);

                curl_close($handler);

                $this->sendPerformed($message);

                return 'OK' === $result ? $this->numberOfRecipients($message) : 0;
            } catch (Exception $e) {
                return 0;
            }
        }
    }
}