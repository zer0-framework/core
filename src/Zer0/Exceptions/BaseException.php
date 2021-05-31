<?php

namespace Zer0\Exceptions;

/**
 * Class BaseException
 *
 * @package Zer0\Exceptions
 */
abstract class BaseException extends \Exception
{
    protected $previousStr;

    /**
     * @return array
     */
    public function __sleep ()
    {
        $this->previousStr = (string)$this->getPrevious();

        return ['message', 'code', 'file', 'line', 'previousString'];
    }

    /**
     * @return string|null
     */
    public function getPreviousStr ()
    {
        return $this->previousStr ?? (string)$this->getPrevious() ?? null;
    }
}
