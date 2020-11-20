<?php
namespace Morbihanet\Modeler;

use Exception;
use GuzzleHttp\Client;

class Fcm extends Table
{
    protected const SEND_URL = 'https://fcm.googleapis.com/fcm/send';

    public static ?string $fcmKey = null;
    public static ?string $application = null;

    public static function getIosToken(string $token): ?string
    {
        $headers = [
            'Authorization' => 'key=' . config('modeler.fcm_key', static::getFcmKey()),
            'Content-Type'  => 'application/json',
        ];

        $fields = [
            "application" => config('modeler.fcm_application', static::getApplication()),
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

    public static function send($token, string $title, string $body, ?array $data = null)
    {
        $headers   = [];
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: key=' . config('modeler.fcm_key', static::getFcmKey());

        curl_setopt($ch = curl_init(), CURLOPT_URL, static::SEND_URL);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'to' => $token,
            'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default', 'badge' => '1',],
            'priority' => 'high',
            'data' => $data,
        ]));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!$response = curl_exec($ch)) {
            return curl_error($ch);
        }

        curl_close($ch);

        return true;
    }

    public static function getFcmKey(): ?string
    {
        return static::$fcmKey;
    }

    public static function setFcmKey(?string $fcmKey): void
    {
        static::$fcmKey = $fcmKey;
    }

    public static function getApplication(): ?string
    {
        return static::$application;
    }

    public static function setApplication(?string $application): void
    {
        static::$application = $application;
    }
}
