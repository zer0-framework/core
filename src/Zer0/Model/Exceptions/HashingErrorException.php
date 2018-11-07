<?php

namespace Zer0\Model\Exceptions;

/**
 * Class HashingErrorException
 * @package Zer0\Model\Exceptions
 */
class HashingErrorException extends InvalidValueException
{
    protected $type = 'hashingError';
}
