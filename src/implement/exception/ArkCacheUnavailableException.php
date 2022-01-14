<?php

namespace sinri\ark\cache\implement\exception;

use Throwable;

/**
 * @since 2.6
 */
class ArkCacheUnavailableException extends ArkCacheException
{
    protected $cacheInstanceName;
    protected $key;
    protected $reportTimestamp;

    public function __construct(string $cacheInstanceName, string $key, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            "ArkCache Instance [$cacheInstanceName] now has not available cache for key [$key], "
            . "on " . date('Y-m-d H:i:s') . " (" . time() . ")",
            $code,
            $previous
        );
        $this->cacheInstanceName = $cacheInstanceName;
        $this->key = $key;
        $this->reportTimestamp = time();
    }

    /**
     * @return string
     */
    public function getCacheInstanceName(): string
    {
        return $this->cacheInstanceName;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return int
     */
    public function getReportTimestamp(): int
    {
        return $this->reportTimestamp;
    }


}