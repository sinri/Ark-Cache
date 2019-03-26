<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2018/9/7
 * Time: 10:43
 */

namespace sinri\ark\cache\implement;


use sinri\ark\cache\ArkCache;
use sinri\ark\cache\implement\exception\ArkCacheInvalidArgumentException;

class ArkFileCache extends ArkCache
{

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
            @mkdir($cacheDir, 0777, true);
        }
        $this->cacheDir = $cacheDir;
    }

    protected function validateObjectKey($key)
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $key)) {
            return true;
        }
        return false;
    }

    protected function getTimeLimitFromObjectPath($path)
    {
        $parts = explode('.', $path);
        $limit = $parts[count($parts) - 1];
        return $limit;
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
                $deleted = unlink($path);
                if (!$deleted) {
                    $all_deleted = false;
                }
            }
        }
        return $all_deleted;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
        $data = file_get_contents($path);
        $object = unserialize($data);
        return $object;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
        $done = file_put_contents($path, $data);
        if ($this->fileMode !== null) chmod($path, $this->fileMode);
        return $done ? true : false;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        if (!$this->validateObjectKey($key)) throw new ArkCacheInvalidArgumentException("KEY INVALID");
        array_map('unlink', glob($this->cacheDir . '/' . $key . '.*'));
        return true;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $list = glob($this->cacheDir . '/*.*');
        if (empty($list)) return true;
        $all_deleted = true;
        foreach ($list as $path) {
            $deleted = unlink($path);
            if (!$deleted) {
                $all_deleted = false;
            }
        }
        return $all_deleted;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
     * @throws \Psr\SimpleCache\InvalidArgumentException
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