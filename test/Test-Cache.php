<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2018/2/24
 * Time: 10:32
 */

use sinri\ark\cache\implement\ArkFileCache;

require_once __DIR__ . '/../vendor/autoload.php';
//require_once __DIR__ . '/../autoload.php';


$cache = new ArkFileCache(__DIR__ . '/cache');
//$cache = new \sinri\ark\cache\implement\ArkDummyCache();

$cache->setUseRawPHPForFileSystem(false);

$cache->saveObject("key", "value", 3600);
var_dump($cache->getObject("key"));
$cache->removeObject("key");
$cache->removeExpiredObjects();

var_dump($cache->getObject('not exist'));
var_dump($cache->getObject('not-exist'));
var_dump($cache->getObject('not_exist'));

$cache->saveObject('soon', time(), 5);
for ($i = 0; $i < 10; $i++) {
    echo "i=$i : " . PHP_EOL;
    var_dump($cache->getObject('soon'));
    sleep(1);
}