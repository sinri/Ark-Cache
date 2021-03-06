<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2018/9/7
 * Time: 10:43
 */

namespace sinri\ark\cache\implement;


use DateInterval;
use Psr\SimpleCache\InvalidArgumentException;
use sinri\ark\cache\ArkCache;
use sinri\ark\cache\implement\exception\ArkCacheInvalidArgumentException;

class ArkFileCache extends ArkCache
{
    /**
     * @var bool
     * @since 2.4
     */
    protected $useRawPHPForFileSystem = true;
    protected $cacheDir;
    protected $fileMode = null;

    /**
     * ArkFileCache constructor.
     * @param string $cacheDir
     * @param null|int $fileMode such as 0777
     */
    public function __construct($cacheDir, $fileMode = null)
    {
        $this->fileMode = $fileMode;
        $this->setCacheDir($cacheDir);//should be overrode by setter
    }

    /**
     * @return bool
     * @since 2.4
     */
    public function isUseRawPHPForFileSystem()
    {
        return $this->useRawPHPForFileSystem;
    }

    /**
     * @param bool $useRawPHPForFileSystem
     * @return ArkFileCache
     * @since 2.4
     */
    public function setUseRawPHPForFileSystem($useRawPHPForFileSystem): ArkFileCache
    {
        $this->useRawPHPForFileSystem = $useRawPHPForFileSystem;
        return $this;
    }

    /**
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @param string $cacheDir
     */
    public function setCacheDir($cacheDir)
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
    public function removeExpiredObjects()
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

    protected function getTimeLimitFromObjectPath($path)
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
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        if (!$this->validateObjectKey($key)) throw new ArkCacheInvalidArgumentException("KEY INVALID");
        $list = glob($this->cacheDir . '/' . $key . '.*');
        if (count($list) === 0) {
            return $default;
        }
        $path = $list[0];
        $limit = $this->getTimeLimitFromObjectPath($path);
        if ($limit < time()) {
            $this->delete($key);
            return $default;
        }
        $data = @file_get_contents($path);
        // There is a very strange case:
        // 2020-06-28 18:33:28 [warning] E_WARNING .../ArkFileCache.php@111 file_get_contents(.../KEY.1593340528):
        // failed to open stream: No such file or directory
        // Try to fix it @since 2.3
        if ($data === false) {
            return $default;
        }
        return unserialize($data);
    }

    protected function validateObjectKey($key)
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $key)) {
            return true;
        }
        return false;
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
    public function delete($key)
    {
        if (!$this->validateObjectKey($key)) throw new ArkCacheInvalidArgumentException("KEY INVALID");
        $items = glob($this->cacheDir . '/' . $key . '.*');
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
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        $done = false;
        foreach ($values as $key => $value) {
            $done = $this->set($key, $value, $ttl);
            if (!$done) return $done;
        }
        return $done;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->validateObjectKey($key)) throw new ArkCacheInvalidArgumentException("KEY INVALID");
        $data = serialize($value);
        $this->delete($key);
        if ($ttl === null) {
            $life = 0;
        } elseif (is_a($ttl, '\DateInterval')) {
            $life = self::turnDateIntervalToSeconds($ttl);
        } else {
            $life = intval($ttl, 10);
        }
        $file_name = $key . '.' . ($life <= 0 ? '0' : time() + $life);
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
        return $done ? true : false;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        $done = false;
        foreach ($keys as $key) {
            $done = $this->delete($key);
            if (!$done) return $done;
        }
        return $done;
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
        if (!$this->validateObjectKey($key)) throw new ArkCacheInvalidArgumentException("KEY INVALID");
        $list = glob($this->cacheDir . '/' . $key . '.*');
        if (count($list) === 0) {
            return false;
        }
        $path = $list[0];
        $limit = $this->getTimeLimitFromObjectPath($path);
        if ($limit < time()) {
            $this->delete($key);
            return false;
        }
        return true;
    }
}