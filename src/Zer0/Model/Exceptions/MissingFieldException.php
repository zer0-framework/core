<?php

namespace Zer0\Model\Exceptions;

/**
 * Class MissingFieldException
 * @package Zer0\Model\Exceptions
 */
class MissingFieldException extends ValidationErrorException
{
    protected $type = 'missingField';
}
