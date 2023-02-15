<?php
require_once './vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_path = './stalcraft-database/ru/';
$result_path = './result/';


//listing with all item references
$listing = json_decode(file_get_contents("$db_path/listing.json"), true);
foreach ($listing as $item_ref){
    $item = json_decode(file_get_contents($db_path.$item_ref['data']), true);
    //fix naming
    $item['category'] = str_replace('combined','mixed' , $item['category']);
    $item['category'] = str_replace('discoverer','research' , $item['category']);
    $item['category'] = str_replace('scientist','scientific' , $item['category']);
    $item['category'] = str_replace('gravity','gravitational' , $item['category']);
    $item['category'] = str_replace('butt','stock' , $item['category']);
    $item['category'] = str_replace('collimator_sights','sight' , $item['category']);
    $item['category'] = str_replace('pistol_handle','handle' , $item['category']);
    $item['category'] = str_replace('bullet','ammo' , $item['category']);
    $item['name']['lines']['en'] = str_replace('/', ' ', $item['name']['lines']['en']);
    if($item['category'] == 'armor/scientific')
        $item['name']['lines']['en'] = str_replace('MIS', 'KIM', $item['name']['lines']['en']);
    //parse to template
    $template = [];
    $template += ['id' => $item['id']];
    $template += ['name' => $item['name']['lines']['en']];
    $template += ['name' => $item['category']];
    foreach ($item['infoBlocks'] as $infoBlock){
        if(isset($infoBlock['type'])){
            if($infoBlock['type'] == 'list'){
                foreach ($infoBlock['elements'] as $element){
                    if($element['type'] == 'key-value'){
                        if(isset($element['key']['lines']) and isset($element['value']['lines']))
                            $template += [ToCamelCase($element['key']['lines']['en']) => $element['value']['lines']['en']];
                    }
                    if($element['type'] == 'numeric'){
                        $template += [ToCamelCase($element['name']['lines']['en']) => $element['value']];
                    }
                    if($element['type'] == 'text' and str_contains($element['text']['key'], 'description')){
                        $template += ['text' => $element['text']['lines']['en']];
                    }

                }
            }
            if($infoBlock['type'] == 'damage'){
                $template += array_slice($infoBlock, 1);
            }
        }


    }
    if (!is_dir($result_path.$item['category'])) {
        // dir doesn't exist, make it
        mkdir($result_path.$item['category'], 0777, true);
    }
    file_put_contents($result_path.$item['category'].'/'.$item['name']['lines']['en'].'.asset', \Symfony\Component\Yaml\Yaml::dump($template));

}
function ToCamelCase($string){
    // Max durability -> maxDurability
    $words = explode(' ',strtolower($string));
    $humps = array_slice($words, 1);
    $humps = array_map('ucfirst', $humps);
    array_unshift($humps , $words[0]);
    return implode($humps);
}
