<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class InternalServerError
 * @package Zer0\HTTP\Exceptions
 */
final class InternalServerError extends HttpError
{
    /**
     * @var int
     */
    public $httpCode = 500;
}
