<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class MovedTemporarily
 * @package Zer0\HTTP\Exceptions
 */
final class MovedTemporarily extends Redirect
{
    /**
     * @var int
     */
    public $httpCode = 302;
}
