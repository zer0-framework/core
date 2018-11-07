<?php

namespace Zer0\Cli\Exceptions;

/**
 * Class InternalRedirect
 * @package Zer0\Cli\Exceptions
 */
final class InternalRedirect extends \Exception
{
    /**
     * @var string
     */
    public $controller;

    /**
     * @var string
     */
    public $action;

    /**
     * @var array
     */
    public $args;

    /**
     * @var string
     */
    public $command;

    /**
     * @param string $controller
     * @param string $action
     * @param array $args
     * @return self
     */
    public function set(string $controller, string $action = 'index', array $args = []): self
    {
        $this->controller = $controller;
        $this->action = $action;
        $this->args = $args;
        return $this;
    }

    /**
     * @param string $command
     * @return self
     */
    public function route(string $command): self
    {
        $this->command = $command;
        return $this;
    }
}
