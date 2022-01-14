<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-26
 * Time: 16:34
 */

namespace sinri\ark\cache\implement\exception;


use Psr\SimpleCache\InvalidArgumentException;

/**
 * @since 2.6 changed base class
 */
class ArkCacheInvalidArgumentException extends ArkCacheException implements InvalidArgumentException
{

}