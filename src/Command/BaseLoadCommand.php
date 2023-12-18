<?php

namespace App\Command;

use App\Model\Place;
use App\Parser\ContentParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

use Elastic\Elasticsearch\ClientBuilder;

#[AsCommand(name: 'data:baseLoad')]
class BaseLoadCommand extends Command
{
    private $config;

    private $baseListSrc;

    private $baseListFilter;

    private $baseListFilterAttribute;

    private $baseListLinkPrefix;

    private $baseListOutputFilePath;

    private $baseListOutputFileEncoding;

    private $baseListOutputFileVersion;

    private $baseListOutputFileRootElement;

    private $baseListOutputFileChildElement;

    public function __construct(string $name = null)
    {
        // todo mozno toot je cas na dto
        $this->config = Yaml::parseFile('config/parser.yaml')['list'];
        // todo mozno toot je cas na dto
        $this->baseListSrc = $this->config['link'];
        $this->baseListFilter = $this->config['filter'];
        $this->baseListFilterAttribute = $this->config['filter_attribute'];
        $this->baseListLinkPrefix = $this->config['link_prefix'];
        $this->baseListOutputFilePath = $this->config['output_file']['src'];
        $this->baseListOutputFileEncoding = $this->config['output_file']['encoding'];
        $this->baseListOutputFileVersion = $this->config['output_file']['version'];
        $this->baseListOutputFileRootElement = $this->config['output_file']['root_element'];
        $this->baseListOutputFileChildElement = $this->config['output_file']['child_element'];

        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->generateBaseLinksFile();
        dump($this->baseListSrc);
        return Command::SUCCESS;
    }

    private function trashCan(){
        $basePage = file_get_contents($this->baseListOutputFilePath);

        $movies = new \SimpleXMLElement($basePage);

        $client = ClientBuilder::create()
            ->setHosts(['https://ges.newt.cz'])
            ->setBasicAuthentication('', '')
            ->build();

        for($i = 0; $i < 2; ++$i){

            $params = [
                'index' => 'diving',
                'body'  => [
                    "name" => "name of {$i}",
                    "param_x" => null,
                    "param_y" => null,
                ],
            ];

            $client->index($params);
        }

        return Command::SUCCESS;


//        dump($movies->count());
        $i = 0;
        $limit = 20;
        $progressBar = new ProgressBar($output, $limit);
        $progressBar->start();

        foreach ($movies->url as $link)
        {
            $file = (string) $link;
            $data = $this->parseDetail($file);

            $params = [
                'index' => 'diving',
                'body'  => $data,
            ];

            $client->index($params);

            if($i == $limit) break;
            $progressBar->advance();
            ++$i;
        }

        $progressBar->finish();
    }

    private function parseDetail(string $file)
    {
        $rawData = file_get_contents($file);
        // todo pre linky zapnut
        $rawData = iconv( "CP1250", "UTF-8", $rawData );

        $crawler = new Crawler();
        $crawler->addHtmlContent($rawData);

        $result =[];
        $result['link'] = $file;

        $result['name'] = $crawler->filter('div#hlavicka h1')->text();

        // zakladne info
        $baseInfo = $crawler->filter('div.form-zobr.w3-container.w3-padding-small div.fp')->each(function (Crawler $node, $i) { return $node->text(); });
        ContentParser::parseBaseContent($baseInfo, $result);

        $description = $crawler->filter('div#popis')->text();
        ContentParser::parseDescription($description, $result);

        // link na mapu a gps
        $baseLinks = $crawler->filter('div.form-zobr.w3-container.w3-padding-small.w3-col.m9.l9 div.fp div.pol a')->each(function (Crawler $node, $i) {
            if(strpos($node->attr('href'), 'mapy'))
                return $node->attr('href');
        });
        ContentParser::parseGps($baseLinks, $result);

        // discusion
        $discusion = $crawler->filter('table.TabNic tr td span.small')->each(function (Crawler $node, $i) {
            return strip_tags($node->text());
        });
        ContentParser::parseDiscusion($discusion, $result);

        // temperature
        $temperature = $crawler->filter('div.form-zobr.w3-container.w3-padding-small table tr')->eq(2)->children('td')->each(function (Crawler $node, $i) {
            return $node->text();
        });
        ContentParser::parseTemperature($temperature, $result);

        // images
        $images = $crawler->filter('div#nahledy a img')->each(function (Crawler $node, $i) {
            return $this->baseListLinkPrefix . $node->attr('src');
        });
        $result['images'] = $images;

        $iconInfo = $crawler->filter('div#ikony img')->each(function (Crawler $node, $i) {
            $tagName = $this->getStringBetween($node->attr('src'), 'ico/', '_');
            $statusText = $this->getStringBetween($node->attr('src'), '_', '.');

            $status = false;
            if($statusText == 'a'){
                $status = true;
            }

            if($tagName == 'casoveneomezeno') $status = !$status;

            if($statusText == 'x'){
                $status = null;
            }

            return [
                $tagName => [
                    'text' => $node->attr('title'),
                    'status' => $status,
                ]
            ];

        });
        ContentParser::parseParams($iconInfo, $result);

        return $result;
    }

    private function generateBaseLinksFile()
    {
        $basePage = file_get_contents($this->baseListSrc);

        $crawler = new Crawler();
        $crawler->addHtmlContent($basePage);

        $nodeValues = $crawler->filter($this->baseListFilter)->each(function (Crawler $node, $i) {
            return empty($node->attr($this->baseListFilterAttribute)) ? null : $this->baseListLinkPrefix . $node->attr($this->baseListFilterAttribute);
        });

        $nodeValues = array_filter($nodeValues);
        $nodeValues = array_values($nodeValues);

        $this->generateXmlOutputFile($nodeValues);
    }

    private function getStringBetween($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    private function generateXmlOutputFile($data)
    {
        $dom = new \DOMDocument();
        $dom->encoding = $this->baseListOutputFileEncoding;
        $dom->xmlVersion = $this->baseListOutputFileVersion;
        $dom->formatOutput = true;

        $root = $dom->createElement($this->baseListOutputFileRootElement);

        foreach ($data as $link){
            $movie_node = $dom->createElement($this->baseListOutputFileChildElement, $link);
            $root->appendChild($movie_node);
        }

        $dom->appendChild($root);
        $dom->save($this->baseListOutputFilePath);
    }
}