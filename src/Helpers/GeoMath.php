<?php

namespace App\Helpers;

class GeoMath
{
    public const EARTH_RADIUS = 6371000;

    public static function getAngle($latitudeFrom, $longitudeFrom,$latitudeTo,$longitudeTo) {

        $z = $longitudeTo - $longitudeFrom;

        $x = cos($latitudeTo) * sin($z);
        $y = cos($latitudeFrom) * sin($latitudeTo)
            - sin($latitudeFrom) * cos($latitudeTo) * cos($z);

        $b = atan2($x,$y);
        $deg = rad2deg($b);

        // post funcion
        $circle = 360;
        if($deg < 1){
            // pretoze je zaporne cislo inak by som odcital
            $deg = $circle + $deg;
        }

        return round($deg);
    }

    public static function getDistance2(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo, int $earthRadius = self::EARTH_RADIUS): float {

    }

    public static function getDistance(float $A, float $B, float $C, float $D, int $earthRadius = self::EARTH_RADIUS): float
    {
        dump('---------------------------------');

        $X = $C - $A;
        $Y = $D - $B;



        $si = pow(sin($X / 2), 2);

        $xx = $X*180/pi();
        dump('sin: ' . $si);

        dump('---------------------------------');
        $angle = 2 * asin(sqrt(pow(sin($X / 2), 2) +
                cos($A) * cos($C) * pow(sin($Y / 2), 2)));
dump('enbllle: ' . $angle);
        return $angle * $earthRadius; // in meters
    }
}