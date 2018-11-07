<?php

namespace Zer0\Mailer;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Base
 * @package Zer0\Mailer
 */
abstract class Base
{
    /**
     * @var ConfigInterface
     */
    public $config;

    /**
     *  Constructor.
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param mixed $additional_headers
     * @param string|null $additional_parameters
     */
    abstract public function send(
        string $to,
        string $subject,
        string $message,
        $additional_headers = null,
        string $additional_parameters = null
    ): void;
}
