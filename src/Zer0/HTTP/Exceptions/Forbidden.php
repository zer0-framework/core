<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class Forbidden
 * @package Zer0\HTTP\Exceptions
 */
final class Forbidden extends HttpError
{
    /**
     * @var int
     */
    public $httpCode = 403;
}
