<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2018/9/7
 * Time: 10:43
 */

namespace sinri\ark\cache\implement;


use Psr\SimpleCache\InvalidArgumentException;
use sinri\ark\cache\Ark64Helper;
use sinri\ark\cache\ArkCache;
use sinri\ark\cache\implement\exception\ArkCacheInvalidArgumentException;
use sinri\ark\cache\implement\exception\ArkCacheUnavailableException;

class ArkFileCache extends ArkCache
{
    /**
     * @var bool
     * @since 2.4
     */
    protected $useRawPHPForFileSystem = true;
    /**
     * @var string
     */
    protected $cacheDir;
    /**
     * @var int|null
     */
    protected $fileMode = null;

    /**
     * ArkFileCache constructor.
     * @param string $cacheDir
     * @param int|null $fileMode such as 0777
     * @param string|null $cacheName
     */
    public function __construct(string $cacheDir, int $fileMode = null, string $cacheName = null)
    {
        parent::__construct($cacheName);
        $this->fileMode = $fileMode;
        $this->setCacheDir($cacheDir);//should be overridden by setter
    }

    /**
     * @return bool
     * @since 2.4
     */
    public function isUseRawPHPForFileSystem(): bool
    {
        return $this->useRawPHPForFileSystem;
    }

    /**
     * @param bool $useRawPHPForFileSystem
     * @return ArkFileCache
     * @since 2.4
     */
    public function setUseRawPHPForFileSystem(bool $useRawPHPForFileSystem): ArkFileCache
    {
        $this->useRawPHPForFileSystem = $useRawPHPForFileSystem;
        return $this;
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * @param string $cacheDir
     */
    public function setCacheDir(string $cacheDir)
    {
        if (!file_exists($cacheDir)) {
            if ($this->useRawPHPForFileSystem) {
                @mkdir($cacheDir, 0777, true);
            } else {
                exec('mkdir -p ' . escapeshellarg($cacheDir));
                exec('chmod -R 777 ' . escapeshellarg($cacheDir));
            }
        }
        $this->cacheDir = $cacheDir;
    }

    /**
     * @return bool
     */
    public function removeExpiredObjects(): bool
    {
        $list = glob($this->cacheDir . '/*.*');
        if (empty($list)) return true;
        $all_deleted = true;
        foreach ($list as $path) {
            $limit = $this->getTimeLimitFromObjectPath($path);
            if ($limit < time()) {
                if ($this->useRawPHPForFileSystem) {
                    $deleted = @unlink($path);
                } else {
                    exec('rm ' . escapeshellarg($path), $ignored, $deleted);
                    $deleted = ($deleted === 0);
                }
                if (!$deleted) {
                    $all_deleted = false;
                }
            }
        }
        return $all_deleted;
    }

    protected function getTimeLimitFromObjectPath(string $path)
    {
        $parts = explode('.', $path);
        return $parts[count($parts) - 1];
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        if ($this->useRawPHPForFileSystem) {
            $list = glob($this->cacheDir . '/*.*');
            if (empty($list)) return true;
            $all_deleted = true;
            foreach ($list as $path) {
                $deleted = @unlink($path);
                if (!$deleted) {
                    $all_deleted = false;
                }
            }
            return $all_deleted;
        } else {
            exec('rm ' . escapeshellarg($this->cacheDir . '/*.*'), $ignored, $returnVar);
            return $returnVar === 0;
        }
    }

    /**
     * @param string $encodedKey
     * @return bool
     * @since 2.6
     */
    protected function deleteByEncodedKey(string $encodedKey): bool
    {
        $items = glob($this->cacheDir . '/' . $encodedKey . '.*');

        if (empty($items)) return true;
        foreach ($items as $item) {
            if ($this->useRawPHPForFileSystem) {
                if (file_exists($item)) {
                    @unlink($item); // let us ignore the warnings!
                }
            } else {
                exec('if [ -f ' . escapeshellarg($item) . ' ];then rm ' . escapeshellarg($item) . ';fi;');
            }
        }
        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key): bool
    {
        if (!is_string($key) && !is_numeric($key)) {
            throw new ArkCacheInvalidArgumentException();
        }
        $encodedKey = Ark64Helper::encode($key);
        return $this->deleteByEncodedKey($encodedKey);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        try {
//        if (!$this->validateObjectKey($key)) throw new ArkCacheInvalidArgumentException("KEY INVALID");
            $encodedKey = Ark64Helper::encode($key);
        } catch (\InvalidArgumentException $e) {
            throw new ArkCacheInvalidArgumentException($e->getMessage());
        }
        $list = glob($this->cacheDir . '/' . $encodedKey . '.*');
        if (count($list) === 0) {
            return false;
        }
        $path = $list[0];
        $limit = $this->getTimeLimitFromObjectPath($path);
        if ($limit < time()) {
            $this->deleteByEncodedKey($encodedKey);
            return false;
        }
        return true;
    }

    /**
     * @return array
     * @since 2.6
     */
    public function getCurrentCacheMap(): array
    {
        $list = glob($this->cacheDir . '/*.*');
        $encodedKeys = [];
        foreach ($list as $item) {
            if (preg_match('/\/(.+)\.(\d+)$/', $item, $matches)) {
                $encodedKey = $matches[1];
                $life = $matches[2];
                if ($life > 0 && $life < time()) {
                    continue;
                }

                $encodedKeys[] = $encodedKey;
            }
        }
        $map = [];
        foreach ($encodedKeys as $encodedKey) {
            try {
                $v = $this->read($encodedKey);
                $map[Ark64Helper::decode($encodedKey)] = $v;
            } catch (ArkCacheUnavailableException $e) {
            }
        }
        return $map;
    }

    public function read(string $key)
    {
        $encodedKey = Ark64Helper::encode($key);
        $list = glob($this->cacheDir . '/' . $encodedKey . '.*');
        if (count($list) === 0) {
            throw new ArkCacheUnavailableException($this->getCacheName(), $key);
        }
        if (count($list) > 1) {
            // fix the bug when only everlasting cache is there
            rsort($list);
        }
        $path = $list[0];
        $limit = $this->getTimeLimitFromObjectPath($path);
        if ($limit < time()) {
            $this->deleteByEncodedKey($encodedKey);
            throw new ArkCacheUnavailableException($this->getCacheName(), $key);
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new ArkCacheUnavailableException($this->getCacheName(), $key);
        }
        return unserialize($data);
    }

    public function write(string $key, $value, int $lifeInSeconds): bool
    {
        $encodedKey = Ark64Helper::encode($key);
        $data = serialize($value);

        $this->deleteByEncodedKey($encodedKey);

        $file_name = $encodedKey . '.' . ($lifeInSeconds <= 0 ? '0' : time() + $lifeInSeconds);
        $path = $this->cacheDir . '/' . $file_name;
        $done = @file_put_contents($path, $data);
        if ($done !== false && $this->fileMode !== null) {
            if ($this->useRawPHPForFileSystem) {
                // @since 2.3 omit the warning
                @chmod($path, $this->fileMode);
            } else {
                exec('chmod ' . decoct($this->fileMode) . ' ' . escapeshellarg($path));
            }
        }
        return (bool)$done;
    }
}