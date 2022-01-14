<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2018/9/7
 * Time: 10:42
 */

namespace sinri\ark\cache\implement;


use sinri\ark\cache\ArkCache;
use sinri\ark\cache\implement\exception\ArkCacheUnavailableException;

class ArkDummyCache extends ArkCache
{

    /**
     * @return bool
     */
    public function removeExpiredObjects(): bool
    {
        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     */
    public function delete($key)
    {
        return true;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        return true;
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
     */
    public function has($key)
    {
        return false;
    }

    public function getCurrentCacheMap(): array
    {
        return [];
    }

    public function read(string $key)
    {
        throw new ArkCacheUnavailableException($this->getCacheName(), $key);
    }

    public function write(string $key, $value, int $lifeInSeconds): bool
    {
        return false;
    }
}