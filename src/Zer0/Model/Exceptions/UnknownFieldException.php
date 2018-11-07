<?php

namespace Zer0\Model\Exceptions;

/**
 * Class UnknownFieldException
 * @package Zer0\Model\Exceptions
 */
class UnknownFieldException extends InvalidValueException
{
    protected $type = 'unknownField';
}
