<?php
namespace Morbihanet\Modeler;

class Fcm extends Table
{
    protected const SEND_URL = 'https://fcm.googleapis.com/fcm/send';

    public static function send($token, string $title, string $body)
    {
        $headers   = [];
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: key=' . config('modeler.fcm_key');

        curl_setopt($ch = curl_init(), CURLOPT_URL, static::SEND_URL);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'to' => $token,
            'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default', 'badge' => '1',],
            'priority' => 'high',
        ]));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!$response = curl_exec($ch)) {
            return curl_error($ch);
        }

        curl_close($ch);

        return true;
    }
}
