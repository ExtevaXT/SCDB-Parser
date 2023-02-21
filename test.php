<?php

use Symfony\Component\Yaml\Yaml;
require_once './vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$yaml = Yaml::parseFile('./result/Items/Attachment/Barrel/3 Port Mini Compensator.asset.meta');
var_dump($yaml);