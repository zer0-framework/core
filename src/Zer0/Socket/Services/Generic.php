<?php

namespace Zer0\Socket\Services;

use Zer0\Socket\Socket;

/**
 * Class Generic
 * @package Zer0\Socket\Services
 */
abstract class Generic
{
    /**
     * @var Socket
     */
    protected $socket;


    /**
     * Generic constructor.
     * @param Socket $socket
     */
    public function __construct($socket)
    {
        $this->socket = $socket;
        $this->init();
    }

    /**
     * Constructor
     */
    protected function init()
    {
    }

    /**
     *
     */
    public function finish()
    {
        $this->onFinish();
    }

    /**
     *
     */
    protected function onFinish()
    {
    }
}
