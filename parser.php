<?php

use Symfony\Component\Yaml\Yaml;

require_once './vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_path = './stalcraft-database/ru/';
$asset_path = './assets/';
$result_path = './result/';

const sprite_fileID = 21300000;
const asset_fileID = 11400000;
const script_fileID = 11500000;
const ammo_guid = 'ebbf3d4b1d1a4412ba55eb0813d3fa87';
const attachment_guid = '87ae8f2a691b5974289ba8a2d2fd217c';
const container_guid = '08ced404c58c31e4ab5f6795ae9ed896';
const equipment_guid = '90c06992fabc4400c8c2e33ff979c9e2';
const device_guid = '07dbc71676f2aaf44a61ae8a445d9051';
const grenade_guid = 'ed7a33bc444938c4daeeb785a5c3728e';
const melee_guid = 'd2dab61ac11af4e81a71e209d3f091f2';
const medicine_guid = '8fbc25bef1e7c4f4aa348edf964275e9';
const shotgun_guid = 'bdae6158522d46441a5aace99f60b060';
const weapon_guid = 'fa99cbcb882d44b2984928ebe183fb04';
const item_guid = '6ca52a2d0c310fc4ca4d26b3c6ed943a';

$arr = [];

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
    $template += ['fullName' => $item['name']['lines']['en']];
    $template += ['pathCategory' => $item['category']];
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
                        $element['name']['key'] = str_replace('artefakt_heal', 'heal', $element['name']['key']);
                        if(!str_contains($element['name']['key'],'stalker.artefact_properties.factor.')){
                            //fix naming and remove excess vars
                            if($item['category'] == 'Ammo') {
                                //event ammo?
                                str_replace('damage_type.default', 'absoluteDamage',$element['name']['key']);
                            }
                            $name = KeyToVar($element['name']['key']);
                            $name = str_replace('direct','damage', $name);
                            if(!str_contains($name,'durability')){
                                $template += [$name => $element['value']];
                            }
                        }
                        else{
                            $template += [KeyToVar($element['name']['key']) => [$element['value']]];
                        }

                    }
                    if($element['type'] == 'range'){
                        //min max bonus
                        //burn_dmg_factor -> burnDmgFactor
                        //without this shit
                        $element['name']['key'] = str_replace('artefakt_heal', 'heal', $element['name']['key']);
                        if(!str_contains($element['name']['key'],'lifesaver'))
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
                if(str_contains($infoBlock['text']['key'], 'description')
                or str_contains($infoBlock['text']['key'], 'additional_stats_tip'))
                    $template += ['text' => $infoBlock['text']['lines']['en']];
                //armor compatibles
                if(str_contains($infoBlock['text']['key'], 'compatibility.backpacks'))
                    $template += ['compatibleBackpacks' => KeyToVar($infoBlock['text']['key'])];
                if(str_contains($infoBlock['text']['key'], 'compatibility.containers'))
                    $template += ['compatibleContainers' => KeyToVar($infoBlock['text']['key'])];
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
    else{
        //script assignment
        $template += ['m_EditorClassIdentifier'=> null];
        $template += ['m_Name'=> $item['name']['lines']['en']];
        $template += ['m_Script'=>['fileID'=>script_fileID, 'guid' => ScriptGUID($item['category']), 'type' => 3]];
        $template += ['m_EditorHideFlags'=> 0];
        $template += ['m_Enabled'=> 1];
        $template += ['m_GameObject'=> ['fileID'=>0]];
        $template += ['m_PrefabAsset'=> ['fileID'=>0]];
        $template += ['m_PrefabInstance'=> ['fileID'=>0]];
        $template += ['m_CorrespondingSourceObject'=> ['fileID'=>0]];
        $template += ['m_ObjectHideFlags'=> 0];
    }
    $final = Yaml::dump(['MonoBehaviour'=>$template], 2);
    //need to bring it back
    $final = '%YAML 1.1
%TAG !u! tag:unity3d.com,2011:
--- !u!114 &11400000
'.$final;

    file_put_contents($result_path.$item['category'].'/'.$item['name']['lines']['en'].'.asset', $final);
}
var_dump_pre($arr);
echo 'ok';



function ScriptGUID($category){
    if(str_contains('Other', $category) or $category == 'Misc') return item_guid;
    if(str_contains('Armor', $category) or $category == 'Backpack') return equipment_guid;
    if(str_contains('Attachment', $category)) return attachment_guid;
    if(str_contains('Weapon', $category)){
        if(str_contains('Device', $category)) return device_guid;
        if(str_contains('Melee', $category)) return melee_guid;
        if(str_contains('Shotgun', $category)) return shotgun_guid;
        return weapon_guid;
    }
    if($category == 'Container') return container_guid;
    if($category == 'Ammo') return ammo_guid;
    if($category == 'Medicine') return medicine_guid;
    if($category == 'Grenade') return grenade_guid;
    return 0;
}
function GUID()
{
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}


//helpers
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
//debug
function var_dump_pre($mixed = null) {
    echo '<pre>';
    var_dump($mixed);
    echo '</pre>';
    return null;
}
function DebugArrFill($arr, $category, $needle, $key){
    if(str_contains($category,$needle)){
        if(!in_array($key.' / '. KeyToVar($key), $arr, true)){
            array_push($arr,$key.' / '. KeyToVar($key));
        }
    }
}