<?php

namespace App\Parser;

class ContentParser
{
    public static function parseBaseContent(array $input, &$result){
        $result['country'] = self::removeSubstring('Stát', $input[0]);
        $result['region'] = self::removeSubstring('Oblast', $input[1]);
        $result['type'] = self::removeSubstring('Typ', $input[2]);
        $result['altitude'] = self::getNumberFromString($input[3]);
        $result['maximumDepth'] = self::getNumberFromString($input[4]);
        $result['averageVisibility'] = self::getNumberFromString($input[6]);

        $scoreText = isset($input[7])? self::removeSubstring('Hodnocení lokality -', $input[7]) : null;
        $result['score'] = [
            'status' => self::removeBraces($scoreText),
            'count' => self::getNumberFromString($scoreText),
        ];
    }

    public static function parseDiscusion($input, &$result)
    {
        $discusion = [];

        foreach ($input as $node){
            $discusion[] = self::removeBraces($node);
        }
        $result['discusion'] = $discusion;
    }

    public static function parseDescription($input, &$result)
    {
        $result['description'] = self::removeSubstring('Popis lokality', $input);
    }

    private const TRANSFORM = [
        'omezenypristup' => 'access_limit',
        'placeno' => 'payed',
        'casoveneomezeno' => 'time_limit',
        'parkovani' => 'parking',
        'prevlekani' => 'prevlekani',
        'kompresor' => 'compresor',
        'velkeskupiny' => 'big_groups',
        'ubytovani' => 'accomondation',
        'kempovani' => 'camping',
        'obcerstveni' => 'buffet',
        'koupani' => 'swimming',
        'wc' => 'toilet',
    ];

    public static function parseParams($input, &$result){
        foreach ($input as $item){
            foreach(self::TRANSFORM as $originName => $realName){
                if(array_key_exists($originName, $item)){
                    $result['param_'.$realName] = $item[$originName]['status'];
                }
            }
        }
    }

    public static function parseTemperature($input, &$result)
    {
        $temps = [];

        foreach ($input as $i => $temp){

            $monthNum  = $i+1;
            $dateObj   = \DateTime::createFromFormat('!m', $monthNum);
            $monthName = $dateObj->format('F');
            $temps[$monthName] = self::getNumberFromString($temp);
        }

        $result['temperature'] = $temps;
    }
    public static function parseGps($input, &$result)
    {
        $baseLinks = array_filter($input);
        if(empty($baseLinks)){
            $result['mapLink'] = null;
            $result['location'] = null;
        } else {
            $baseLinks = end($baseLinks);
            $result['mapLink'] = $baseLinks;
            $coors = explode('id=', $baseLinks)[1];
            $coors = explode('%2C', $coors);

            $result['location'] = [
                'lat' => $coors[0],
                'lon' => $coors[1],
            ];
        }
    }

    private static function removeBraces($body){
        return trim(preg_replace('/[\[{\(].*?[\]}\)]/', '', $body));
    }

    private static function removeSubstring($search, $body){
        return trim(str_replace($search,'', $body));
    }

    private static function getNumberFromString($str) {
        preg_match('/\d+/', $str, $matches);
        return isset($matches[0]) ? (int) $matches[0]: null;
    }
}