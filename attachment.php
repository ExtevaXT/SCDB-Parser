<?php
use Symfony\Component\Yaml\Yaml;
require_once './vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

const trim = '%YAML 1.1
%TAG !u! tag:unity3d.com,2011:
--- !u!114 &11400000
';
$db_path = './stalcraft-database/ru/';
$result_path = './result/Items/';

$assets = collect();
$it = new RecursiveDirectoryIterator($result_path);
foreach(new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() == 'asset') {
        $assets->push(Yaml::parse(str_ireplace(trim,'', file_get_contents($file)))['MonoBehaviour']);
    }
}
$listing = json_decode(file_get_contents("$db_path/listing.json"), true);
foreach ($listing as $item_ref){
    $item = json_decode(file_get_contents($db_path.$item_ref['data']), true);
    $item['category'] = Category($item['category']);
    if(($asset_path = $result_path.$item['category'].'/'.Name($item['name']['lines']['en']) .'.asset') != null){
        $asset = Yaml::parse(str_replace(trim, '', file_get_contents($asset_path)))['MonoBehaviour'];
        $assetMeta = Yaml::parse(file_get_contents($result_path.$asset['pathCategory'].'/'.$asset['m_Name'].'.asset.meta'));
        foreach ($item['infoBlocks'] as $infoBlock) {
            if(isset($infoBlock['type']) and $infoBlock['type'] == 'list') {
                foreach ($infoBlock['elements'] as $element) {
                    if($element['type'] == 'item') {
                        if($suitableAsset = $assets->firstWhere('m_Name', $element['name']['lines']['en'])){
                            $suitableAssetMeta = Yaml::parse(file_get_contents($result_path.$suitableAsset['pathCategory'].'/'.$suitableAsset['m_Name'].'.asset.meta'));
                            $ref = ['fileID'=>11400000, 'guid' => $suitableAssetMeta['guid'], 'type'=>2];
                            if(isset($asset['_suitableFor']))
                                array_push($asset['_suitableFor'], $ref);
                            else
                                $asset += ['_suitableFor' => [$ref]];

                            //inject if attachment allowed to weapon, reverse logic
                            $ref = ['fileID'=>11400000, 'guid' => $assetMeta['guid'], 'type'=>2];
                            if(isset($suitableAsset['attachmentsAllowed']))
                                array_push($suitableAsset['attachmentsAllowed'], $ref);
                            else
                                $suitableAsset += ['attachmentsAllowed' => [$ref]];
                            //need to add this new $suitableAsset to $assets collection
                            $assets = $assets->where('m_Name', '!=', $suitableAsset['m_Name'])->push($suitableAsset);
                            //file_put_contents($result_path.$suitableAsset['pathCategory'].'/'.$suitableAsset['m_Name'].'.asset',trim.Yaml::dump(['MonoBehaviour'=>$suitableAsset], 2));
                        }
                    }
                }
            }
        }
        file_put_contents($result_path.$asset['pathCategory'].'/'.$asset['m_Name'].'.asset',trim.Yaml::dump(['MonoBehaviour'=>$asset], 2));
    }

}
foreach ($assets as $asset){
    if(isset($asset['attachmentsAllowed']))
        file_put_contents($result_path.$asset['pathCategory'].'/'.$asset['m_Name'].'.asset',trim.Yaml::dump(['MonoBehaviour'=>$asset], 2));
}
echo "<br>ok";
function Category($string){
    //fix category naming
    $string = str_replace('combined','mixed' , $string);
    $string = str_replace('discoverer','research' , $string);
    $string = str_replace('scientist','scientific' , $string);
    $string = str_replace('gravity','gravitational' , $string);
    $string = str_replace('butt','stock' , $string);
    $string = str_replace('collimator_sights','sight' , $string);
    $string = str_replace('pistol_handle','handle' , $string);
    $string = str_replace('bullet','ammo' , $string);
    $string = str_replace('containers','container' , $string);
    return str_replace('_', ' ', implode('/',array_map('ucfirst', explode('/', $string))));
}
function Name($string){
    //fix item naming
    $string = str_replace("Mosin's", 'Mosin', $string);
    $string = str_replace('WA2000', 'WA 2000', $string);
    $string = str_replace("SMG", 'PP', $string);
    $string = str_replace("Nut", 'Oreh', $string);
    $string = str_replace('/', ' ', $string);
    $string = str_replace('MIS', 'KIM', $string);
    return $string;
}