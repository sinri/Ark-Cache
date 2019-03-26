<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-26
 * Time: 16:34
 */

namespace sinri\ark\cache\implement\exception;


use Exception;
use Psr\SimpleCache\InvalidArgumentException;

class ArkCacheInvalidArgumentException extends Exception implements InvalidArgumentException
{

}