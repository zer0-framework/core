<?php

namespace Zer0\Exceptions;

/**
 * Class BaseException
 * @package Zer0\Exceptions
 */
abstract class BaseException extends \Exception
{
    /**
     * @return array
     */
    public function __sleep()
    {
        return ['message', 'code', 'file', 'line'];
    }
}
