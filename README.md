# Ark-Cache
The cache component for Ark 2

Provided Dummy and File implementation.

Since 2.0, PSR-16 introduced with `Psr\SimpleCache\CacheInterface` supported.

---

Note, if you are in China Mainland, you might need this in composer.json.

````json
{
  "repositories": {
    "packagist": {
      "type": "composer",
      "url": "https://mirrors.aliyun.com/composer/"
    }
  }
}
````

Since 2.6, PSR-16 is still supported with `Psr\SimpleCache\CacheInterface` but not the core usage.