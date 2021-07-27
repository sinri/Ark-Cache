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

/**
 * Interface ArkCache
 * @package sinri\ark\cache
 * @since 2.0, PSR-16 introduced with Psr\SimpleCache\CacheInterface support
 */
abstract class ArkCache implements CacheInterface
{
    /**
     * @param string $key
     * @param mixed $object
     * @param int $life 0 for no limit, or seconds
     * @return bool
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
    abstract public function removeExpiredObjects();

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
}