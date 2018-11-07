<?php

namespace Zer0\PubSub\Pools;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\PubSub\MessageInterface;

/**
 * Class BaseAsync
 * @package Zer0\PubSub\Pools
 */
abstract class BaseAsync
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
     *
     */
    abstract public function unsubscribeAll(): void;

    /**
     * @param array $channels
     */
    abstract public function unsubscribe($channels): void;

    /**
     * @param MessageInterface $message
     * @param callable|null $cb
     */
    abstract public function publish(MessageInterface $message, ?callable $cb = null): void;

    /**
     * @param array $channels
     * @param callable $cb
     */
    abstract public function subscribe($channels, callable $cb): void;

    /**
     * @param array $patterns
     * @param callable $cb
     */
    abstract public function psubscribe($patterns, callable $cb): void;
}
