<?php
namespace Morbihanet\Modeler;

use Exception;
use GuzzleHttp\Client;

class Fcm extends Table
{
    protected const SEND_URL = 'https://fcm.googleapis.com/fcm/send';

    public static function getIosToken(string $token): ?string
    {
        $headers = [
            'Authorization' => 'key=' . config('modeler.fcm_key'),
            'Content-Type'  => 'application/json',
        ];

        $fields = [
            "application" => config('modeler.fcm_application'),
            "sandbox" => true,
            "apns_tokens" => [$token]
        ];

        $fields = json_encode($fields);

        try {
            $response = (new Client)->post('https://iid.googleapis.com/iid/v1:batchImport', [
                'headers' => $headers,
                "body" => $fields,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return $data['results'][0]['registration_token'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

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
