<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class Unauthorized
 * @package Zer0\HTTP\Exceptions
 */
final class Unauthorized extends HttpError
{
    /**
     * @var int
     */
    public $httpCode = 401;
}
