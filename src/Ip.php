<?php
namespace Morbihanet\Modeler;

class Ip extends Table
{
    public static function retrieve(bool $canGetLocalIp = true, string $defaultIp = '192.168.1.1'): string
    {
        $ip = '';

        $serverVarKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($serverVarKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $item) {
                    if (
                        static::isValid($item) &&
                        substr($item, 0, 4) !== '127.' &&
                        substr($item, 0, 4) !== '172.' &&
                        substr($item, 0, 4) !== '192.' &&
                        $item !== '::1' &&
                        $item !== '' &&
                        !in_array($item, ['255.255.255.0', '255.255.255.255'])
                    ) {
                        $ip = $item;
                        break;
                    }
                }
            }
        }

        if ($canGetLocalIp && empty($ip)) {
            foreach ($serverVarKeys as $key) {
                if (array_key_exists($key, $_SERVER) === true) {
                    foreach (explode(',', $_SERVER[$key]) as $item) {
                        if (static::isValid($item) && $item !== '') {
                            $ip = $item;

                            break;
                        }
                    }
                }
            }
        }

        return $ip ? $ip : $defaultIp;
    }

    public static function isValid(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return false;
    }

    public static function track(?string $ip = null): ?Item
    {
        $ip = $ip ?? static::retrieve(false);
        
        if (!empty($ip) && !$row = static::whereIp($ip)->first()) {
            $dataRaw   = json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip), true);

            if ($dataRaw && $dataRaw['geoplugin_status'] !== 404) {
                $data = [];

                foreach ($dataRaw as $k => $v) {
                    $data[str_replace('geoplugin_', '', $k)] = $v;
                }

                $data['ip'] = $data['request'];
                $data['bearer'] = Core::bearer();

                unset($data['status'], $data['delay'], $data['credit'], $data['request']);

                $row = static::create($data);
            }
        }

        return $row;
    }
}
