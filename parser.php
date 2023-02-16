<?php

use Symfony\Component\Yaml\Yaml;

require_once './vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_path = './stalcraft-database/ru/';
$asset_path = './assets/';
$result_path = './result/';


//listing with all item references
$listing = json_decode(file_get_contents("$db_path/listing.json"), true);
foreach ($listing as $item_ref){
    $item = json_decode(file_get_contents($db_path.$item_ref['data']), true);
    //fix category naming
    $item['category'] = str_replace('combined','mixed' , $item['category']);
    $item['category'] = str_replace('discoverer','research' , $item['category']);
    $item['category'] = str_replace('scientist','scientific' , $item['category']);
    $item['category'] = str_replace('gravity','gravitational' , $item['category']);
    $item['category'] = str_replace('butt','stock' , $item['category']);
    $item['category'] = str_replace('collimator_sights','sight' , $item['category']);
    $item['category'] = str_replace('pistol_handle','handle' , $item['category']);
    $item['category'] = str_replace('bullet','ammo' , $item['category']);
    $item['category'] = str_replace('containers','container' , $item['category']);
    //fix item naming
    $item['name']['lines']['en'] = str_replace("Mosin's", 'Mosin', $item['name']['lines']['en']);
    $item['name']['lines']['en'] = str_replace('WA2000', 'WA 2000', $item['name']['lines']['en']);
    $item['name']['lines']['en'] = str_replace("SMG", 'PP', $item['name']['lines']['en']);
    $item['name']['lines']['en'] = str_replace("Nut", 'Oreh', $item['name']['lines']['en']);
    $item['name']['lines']['en'] = str_replace('/', ' ', $item['name']['lines']['en']);
    if($item['category'] == 'armor/scientific')
        $item['name']['lines']['en'] = str_replace('MIS', 'KIM', $item['name']['lines']['en']);
    //weapon/assault_rifle -> Weapon/Assault rifle
    $item['category'] = str_replace('_', ' ', implode('/',array_map('ucfirst', explode('/', $item['category']))));

    //parse to template
    $template = [];
    $template += ['id' => $item['id']];
    //item.att.silencer_fa556.name -> silencer_fa556
    $template += ['key' => array_reverse(explode('.',$item['name']['key']))[1]];
    $template += ['name' => $item['name']['lines']['en']];
    $template += ['category' => $item['category']];
    foreach ($item['infoBlocks'] as $infoBlock){
        if(isset($infoBlock['type'])){
            if($infoBlock['type'] == 'list'){
                foreach ($infoBlock['elements'] as $element){
                    if($element['type'] == 'key-value'){
                        if(isset($element['key']['lines']) and isset($element['value']['lines']))
                        {
                            $template += [ToCamelCase($element['key']['lines']['en']) => $element['value']['lines']['en']];
                            if($element['key']['key'] == 'weapon.tooltip.weapon.info.ammo_type'){
                                //need to inline asset references
                                $template += ['requiredAmmo'=> [['fileID'=> 11400000, 'guid' => '999e8dc267ef825418be332578e4e926', 'type'=>2]]];
                            }
                        }
                    }
                    if($element['type'] == 'numeric'){
                        //single bonus
                        if(!str_contains($element['name']['key'],'stalker.artefact_properties.factor.')){
                            //fix naming and remove excess vars
                            $name = KeyToVar($element['name']['key']);
                            $name = str_replace('direct','damage', $name);
                            if(!str_contains($name,'durability'))
                                $template += [$name => $element['value']];
                        }
                        else $template += [KeyToVar($element['name']['key']) => [$element['value']]];
                    }
                    if($element['type'] == 'range'){
                        //min max bonus
                        //burn_dmg_factor -> burnDmgFactor
                        $template += [KeyToVar($element['name']['key']) => [$element['min'], $element['max']]];
                    }
                    //night vision in armor
                    if($element['type'] == 'text'){
                        if(str_contains($element['text']['key'], 'stalker.tooltip.armor_artefact.night_vision'))
                            $template += ['nightVision'=>1];
                    }
                    //attachment feature
                    if($element['type'] == 'item'){
                        //here I should make link to item asset using guid from meta.php
                        $_item = str_replace('/', ' ', $element['name']['lines']['en']);
                        if(isset($template['suitableFor']))
                            array_push($template['suitableFor'], $_item);
                        else
                            $template += ['suitableFor' => [$_item]];
                    }
                }
            }
            if($infoBlock['type'] == 'damage'){
                $template += array_slice($infoBlock, 1);
            }
            if($infoBlock['type'] == 'text'){
                if(str_contains($infoBlock['text']['key'], 'description'))
                    $template += ['text' => $infoBlock['text']['lines']['en']];
                //armor compatibles
                if(str_contains($infoBlock['text']['key'], 'compatible_backpacks'))
                    $template += ['compatibleBackpacks' =>
                        str_replace('general.armor.compatibility.backpacks.', '',$infoBlock['text']['key'])];
                if(str_contains($infoBlock['text']['key'], 'compatible_containers'))
                    $template += ['compatibleContainers' =>
                        str_replace('general.armor.compatibility.containers.', '',$infoBlock['text']['key'])];
            }
        }
    }
    if (!is_dir($result_path.$item['category'])) {
        // dir doesn't exist, make it
        mkdir($result_path.$item['category'], 0777, true);
    }
    //if finds my preset then merge it
    $preset_path = $asset_path.$item['category'].'/'.$item['name']['lines']['en'].'.asset';
    if(file_exists($preset_path)){
        $preset = file_get_contents($preset_path);
        //temporarily remove unity part
        $preset = str_replace('%TAG !u! tag:unity3d.com,2011:', '', $preset);
        $preset = str_replace('--- !u!114 &11400000', '', $preset);
        $preset = Yaml::parse($preset);
        $template = array_merge($preset['MonoBehaviour'], $template);
    }
    $final = Yaml::dump(['MonoBehaviour'=>$template], 2);
    //need to bring it back
    $final = '%YAML 1.1
%TAG !u! tag:unity3d.com,2011:
--- !u!114 &11400000
'.$final;
    file_put_contents($result_path.$item['category'].'/'.$item['name']['lines']['en'].'.asset', $final);
}
echo 'ok';
function KeyToVar($string){
    $keys = explode('.',$string);
    return ToCamelCase(str_replace('_',' ', end($keys)));
}
function ToCamelCase($string){
    // Max durability -> maxDurability
    $words = explode(' ',strtolower($string));
    $humps = array_slice($words, 1);
    $humps = array_map('ucfirst', $humps);
    array_unshift($humps , $words[0]);
    return str_replace('.','',implode($humps));
}
function var_dump_pre($mixed = null) {
    echo '<pre>';
    var_dump($mixed);
    echo '</pre>';
    return null;
}