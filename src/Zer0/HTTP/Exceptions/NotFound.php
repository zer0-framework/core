<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class NotFound
 * @package Zer0\HTTP\Exceptions
 */
class NotFound extends HttpError
{
    /**
     * @var int
     */
    public $httpCode = 404;
}
