<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class BadRequest
 * @package Zer0\HTTP\Exceptions
 */
class BadRequest extends HttpError
{
    /**
     * @var int
     */
    public $httpCode = 400;
}
