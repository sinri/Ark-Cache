<?php


namespace sinri\ark\cache;


use InvalidArgumentException;

class Ark64Helper
{
    /**
     * @param string $raw
     * @return string
     * @since 2.5
     */
    public static function encode(string $raw): string
    {
        $b = base64_encode($raw);
        $b = str_replace('+', '.', $b);
        $b = str_replace('/', '-', $b);
        $b = str_replace('=', '_', $b);
        if (!is_string($b)) {
            throw new InvalidArgumentException(__METHOD__ . ' Cannot Encode [' . $raw . ']');
        }
        return $b;
    }

    /**
     * @param string $encoded
     * @return string
     * @since 2.5
     */
    public static function decode(string $encoded): string
    {
        $b = str_replace('_', '=', $encoded);
        $b = str_replace('-', '/', $b);
        $b = str_replace('.', '+', $b);
        $raw = base64_decode($b);
        if (!is_string($raw)) {
            throw new InvalidArgumentException(__METHOD__ . ' Cannot Decode [' . $encoded . ']');
        }
        return $raw;
    }

}