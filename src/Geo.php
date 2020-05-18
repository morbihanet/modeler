<?php
namespace Morbihanet\Modeler;

class Geo
{
    public static function distance($lng1, $lat1, $lng2, $lat2, bool $kmRender = false)
    {
        $lng1 = floatval(str_replace(',', '.', $lng1));
        $lat1 = floatval(str_replace(',', '.', $lat1));
        $lng2 = floatval(str_replace(',', '.', $lng2));
        $lat2 = floatval(str_replace(',', '.', $lat2));

        $pi80 = M_PI / 180;
        $lat1 *= $pi80;
        $lng1 *= $pi80;
        $lat2 *= $pi80;
        $lng2 *= $pi80;

        $dlat           = $lat2 - $lat1;
        $dlng           = $lng2 - $lng1;
        $a              = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c              = 2 * atan2(sqrt($a), sqrt(1 - $a));

        /* km */
        $earthRadius    = 6372.797;
        $km             = $earthRadius * $c;
        $km             = round($km, 2);

        /* miles */
        $earthRadius    = 3963.1;
        $miles          = $earthRadius * $c;
        $miles          = round($miles, 2);

        return $kmRender ? $km : ['km' => $km, 'miles' => $miles];
    }

    public static function degTorad($deg)
    {
        return $deg * M_PI / 180;
    }

    public static function lonToTileX($lon, int $zoom)
    {
        return floor((($lon + 180) / 360) * pow(2, $zoom));
    }

    public static function latToTileY($lat, int $zoom)
    {
        return floor((1 - log(tan(static::degTorad($lat)) + 1 / cos(static::degTorad($lat))) / M_PI) / 2 * pow(2, $zoom));
    }

    public static function toTile($longitude, $latitude, int $zoom = 15): string
    {
        return 'https://www.google.com/maps/vt/pb=!1m4!1m3!1i'.$zoom.'!2i'.static::lonToTileX($longitude, $zoom).'!3i'.static::latToTileY($latitude, $zoom).'!2m3!1e0!2sm!3i480189574!3m7!2sfr!5e1105!12m4!1e68!2m2!1sset!2sRoadmap!4e1!5m4!1e4!8m2!1e0!1e1!6m7!1e12!2i2!26m1!4b1!39b1!44e1!50e0!23i1358902';
    }

    public static function getBoundingBox($lat, $lng, $distance = 2, bool $km = true): array
    {
        $lat = floatval($lat);
        $lng = floatval($lng);

        $radius = $km ? 6372.797 : 3963.1;

        $due_north  = deg2rad(0);
        $due_south  = deg2rad(180);
        $due_east   = deg2rad(90);
        $due_west   = deg2rad(270);

        $lat_r = deg2rad($lat);
        $lon_r = deg2rad($lng);

        $northmost  = asin(sin($lat_r) * cos($distance / $radius) + cos($lat_r) * sin($distance / $radius) * cos($due_north));
        $southmost  = asin(sin($lat_r) * cos($distance / $radius) + cos($lat_r) * sin($distance / $radius) * cos($due_south));

        $eastmost = $lon_r + atan2(sin($due_east) * sin($distance / $radius) * cos($lat_r), cos($distance / $radius) - sin($lat_r) * sin($lat_r));
        $westmost = $lon_r + atan2(sin($due_west) * sin($distance / $radius) * cos($lat_r), cos($distance / $radius) - sin($lat_r) * sin($lat_r));

        $northmost  = rad2deg($northmost);
        $southmost  = rad2deg($southmost);
        $eastmost   = rad2deg($eastmost);
        $westmost   = rad2deg($westmost);

        if ($northmost > $southmost) {
            $lat1 = $southmost;
            $lat2 = $northmost;
        } else {
            $lat1 = $northmost;
            $lat2 = $southmost;
        }

        if ($eastmost > $westmost) {
            $lon1 = $westmost;
            $lon2 = $eastmost;
        } else {
            $lon1 = $eastmost;
            $lon2 = $westmost;
        }

        return [$lat1, $lon1, $lat2, $lon2];
    }
}