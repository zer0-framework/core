<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class HttpError
 * @package Zer0\HTTP\Exceptions
 */
abstract class HttpError extends \Exception
{
    /**
     * @var int
     */
    public $httpCode;
}
