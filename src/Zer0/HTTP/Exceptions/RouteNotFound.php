<?php

namespace Zer0\HTTP\Exceptions;

use Zer0\Exceptions\BaseException;

/**
 * Class RouteNotFound
 * @package Zer0\HTTP\Exceptions
 */
final class RouteNotFound extends BaseException
{
    /**
     * @var int
     */
    public $httpCode = 403;
}
