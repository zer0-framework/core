<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class MethodNotAllowed
 * @package Zer0\HTTP\Exceptions
 */
final class MethodNotAllowed extends HttpError
{
    /**
     * @var int
     */
    public $httpCode = 405;
}
