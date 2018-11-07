<?php

namespace Zer0\Helpers;

/**
 * Class ErrorsAreExceptions
 * @package Zer0\Helpers
 */
class ErrorsAreExceptions
{
    /**
     *
     */
    public static function makeItSo(): void
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        assert_options(ASSERT_CALLBACK, function() {
           throw new \AssertionError;
        });
    }
}
