<?php

namespace Zer0\Mailer;

/**
 * Class Plain
 * @package Zer0\Mailer
 */
final class Plain extends Base
{
    /**
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param mixed $additional_headers
     * @param string|null $additional_parameters
     */
    public function send(
        string $to,
        string $subject,
        string $message,
        $additional_headers = null,
        string $additional_parameters = null
    ): void {
        mail($to, $subject, $message, $additional_headers, $additional_parameters);
    }
}
