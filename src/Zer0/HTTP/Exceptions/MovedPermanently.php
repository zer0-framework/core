<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class MovedPermanently
 * @package Zer0\HTTP\Exceptions
 */
final class MovedPermanently extends Redirect
{
    /**
     * @var int
     */
    public $httpCode = 301;
}
