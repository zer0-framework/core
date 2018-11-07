<?php

namespace Zer0\Helpers;

/**
 * Class Str
 * @package Zer0\Helpers
 */
class Str
{
    /**
     * Base64 encode for URL
     * @param string $data
     * @return string
     */
    public static function base64UrlEncode(string $data): string
    {
        return strtr(rtrim(base64_encode($data), '='), '+/', '-_');
    }

    /**
     * Base64 decode
     * @param string $base64
     * @return string
     */
    public static function base64UrlDecode(string $base64): string
    {
        return base64_decode(strtr($base64, '-_', '+/'));
    }
}
