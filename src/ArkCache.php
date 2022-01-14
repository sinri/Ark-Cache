<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2018/9/7
 * Time: 10:39
 */

namespace sinri\ark\cache;


use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use sinri\ark\cache\implement\exception\ArkCacheInvalidArgumentException;
use sinri\ark\cache\implement\exception\ArkCacheUnavailableException;

/**
 * Interface ArkCache
 * @package sinri\ark\cache
 * @since 2.0, PSR-16 introduced with Psr\SimpleCache\CacheInterface support
 */
abstract class ArkCache implements CacheInterface
{
    /**
     * @var string
     */
    protected $cacheName;

    public function __construct(string $cacheName = null)
    {
        $this->cacheName = $cacheName ?: uniqid('ArkCacheInstance');
    }

    /**
     * @return string
     */
    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    /**
     * @param string $key
     * @param mixed $object
     * @param int $life 0 for no limit, or seconds
     * @return bool
     * @deprecated since 2.6 use write
     */
    public function saveObject(string $key, $object, int $life = 0): bool
    {
        try {
            return $this->set($key, $object, $life);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @return mixed|false
     * @deprecated since 2.6 use read
     */
    public function getObject(string $key)
    {
        try {
            return $this->get($key, false);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function removeObject(string $key): bool
    {
        try {
            return $this->delete($key);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    abstract public function removeExpiredObjects(): bool;

    /**
     * 1 Year is defined as 365 days and 1 Month is defined as 30 days
     * @param DateInterval $dateInterval
     * @return int turn to seconds
     */
    public static function turnDateIntervalToSeconds(DateInterval $dateInterval): int
    {
        return $dateInterval->y * 365 * 24 * 3600
            + $dateInterval->m * 30 * 24 * 3600
            + $dateInterval->d * 24 * 3600
            + $dateInterval->h * 3600
            + $dateInterval->i * 60
            + $dateInterval->s;
    }

    abstract public function getCurrentCacheMap(): array;

    /**
     * @param string $key
     * @return mixed
     * @throws ArkCacheUnavailableException
     * @since 2.6
     */
    abstract public function read(string $key);

    /**
     * @param string $key
     * @param mixed $value
     * @param int $lifeInSeconds
     * @return bool
     * @since 2.6
     */
    abstract public function write(string $key, $value, int $lifeInSeconds): bool;

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
        if (!is_string($key) && !is_numeric($key)) {
            throw new ArkCacheInvalidArgumentException();
        }
        try {
            return $this->read($key);
        } catch (ArkCacheUnavailableException $e) {
            return $default;
        }
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
        if (!is_string($key) && !is_numeric($key)) {
            throw new ArkCacheInvalidArgumentException();
        }
        if ($ttl === null) {
            $life = 0;
        } elseif (is_a($ttl, DateInterval::class)) {
            $life = self::turnDateIntervalToSeconds($ttl);
        } else {
            $life = intval($ttl);
        }
        return $this->write($key, $value, $life);
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
            if (!$done) return false;
        }
        return $done;
    }

}