<?php
namespace App\Command;
error_reporting(E_ERROR );

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Elastic\Elasticsearch\ClientBuilder;
use App\Helpers\GeoMath;


// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'app:test')]
class TestCommand extends Command
{

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
//        $client = ClientBuilder::create()
//            ->setHosts(['https://ges.newt.cz'])
//            ->setBasicAuthentication('gregor', 'kemp2022')
//            ->build();

// Info API
//        $response = $client->info();

//        $params = [
//            'index' => 'my_index',
//            'body'  => [ 'testField' => 'abc']
//        ];
//
//        $response = $client->index($params);
//        dump($response);


        [$latitudeFrom, $longitudeFrom,$latitudeTo,$longitudeTo] = $this->parseCoordinatesFromXml('public/tmp/coors.xml');
        $bearing = GeoMath::getAngle($latitudeFrom, $longitudeFrom,$latitudeTo,$longitudeTo);
        $distance = GeoMath::getDistance($latitudeFrom, $longitudeFrom,$latitudeTo,$longitudeTo);


        dump('---------------------------------');
        dump($distance);
        dump($bearing);
        dump('---------------------------------');
//
//        $res = atan2(3,2);
//        $res = atan2(3,2)*180/pi();
//        $x = -0.00681261948;
//        $y = 0.05967668696;
//        $res = atan2($y,$x);
//        $res = (0.05967668696 / -0.00681261948) ;
//        dump($res);
//        $z = $y/$x;
//        dump($z);
//        $res = atan($z)+pi();
//        dump($res);
        dump('---------------------------------');
//        dump(atan($z));
//        dump(cos(39.099912) * sin(38.627089) - sin(39.099912) * cos(38.627089) * cos(4.38101));

        return Command::SUCCESS;
    }



    private function parseCoordinatesFromXml($filename)
    {
        $content = $this->loadFile($filename);
        $movies = new \SimpleXMLElement($content);

        // convert from degrees to radians
        // fromula deg2rad($z) = $z*pi()/180
        // formula rad2deg = $z*180/pi()

        return [
            deg2rad((float)$movies->trk->trkseg->trkpt[0]['lat']),
            deg2rad((float)$movies->trk->trkseg->trkpt[0]['lon']),
            deg2rad((float)$movies->trk->trkseg->trkpt[1]['lat']),
            deg2rad((float)$movies->trk->trkseg->trkpt[1]['lon']),
        ];

    }

    private function loadFile(string $filename): string|null
    {
        if(file_exists($filename)){
            $content = file_get_contents($filename);
            return $content;
        }

        return null;
    }



}