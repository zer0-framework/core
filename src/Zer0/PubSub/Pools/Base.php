<?php

namespace Zer0\PubSub\Pools;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\PubSub\Message;
use Zer0\Queue\Exceptions\WaitTimeoutException;
use Zer0\Queue\TaskAbstract;

/**
 * Class Base
 * @package Zer0\PubSub\Pools
 */
abstract class Base
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var App
     */
    protected $app;

    /**
     * Base constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * @param Message $message
     * @return int
     */
    abstract public function publish(Message $message): int;
}
