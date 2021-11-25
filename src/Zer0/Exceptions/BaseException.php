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
        if ($prev = $this->getPrevious()) {
            $this->previousStr = (string)$prev;
        }

        return ['message', 'code', 'file', 'line', 'previousStr'];
    }

    /**
     *
     */
    public function __wakeup (): void
    {
        if ($this->previousStr !== null) {
            $this->message .= PHP_EOL . 'Previous: ' . $this->previousStr;
        }
    }
}
