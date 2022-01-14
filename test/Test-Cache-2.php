<?php

use sinri\ark\cache\implement\ArkFileCache;
use sinri\ark\cache\implement\exception\ArkCacheUnavailableException;

require_once __DIR__ . '/../vendor/autoload.php';
//require_once __DIR__ . '/../autoload.php';


$cache = new ArkFileCache(__DIR__ . '/cache');
//$cache = new \sinri\ark\cache\implement\ArkDummyCache();

$cache->setUseRawPHPForFileSystem(false);

$key = "Case2-A";
$key = "";

$cache->write($key, time(), 0);
try {
    echo time() . " -> " . $cache->read($key) . PHP_EOL;
} catch (ArkCacheUnavailableException $e) {
    echo time() . " -> not cached " . PHP_EOL;
}

for ($i = 0; $i < 4; $i++) {
    $value = time();
    echo time() . " set " . $value . PHP_EOL;
    $cache->write($key, $value, 8);
    try {
        echo time() . " get " . $cache->read($key) . PHP_EOL;
    } catch (ArkCacheUnavailableException $e) {
        echo time() . " -> not cached " . PHP_EOL;
    }
    sleep(5);
}
