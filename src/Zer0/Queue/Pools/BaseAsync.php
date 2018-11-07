<?php

namespace Zer0\Queue\Pools;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Queue\Exceptions\WaitTimeoutException;
use Zer0\Queue\TaskAbstract;

/**
 * Class BaseAsync
 * @package Zer0\Queue\Pools
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
     * @param callable $cb (int $id)
     */
    abstract public function nextId(callable $cb): void;

    /**
     * @param TaskAbstract $task
     * @param int $seconds
     * @param callable $cb (?TaskAbstract $task)
     */
    abstract public function wait(TaskAbstract $task, int $seconds, callable $cb): void;

    /**
     * @param TaskAbstract $task
     * @param callable $cb (?TaskAbstract $success, BaseAsync $pool)
     */
    abstract public function enqueue(TaskAbstract $task, ?callable $cb = null): void;

    /**
     * @param TaskAbstract $task
     * @param int $seconds
     * @param callable $cb
     */
    public function enqueueWait(TaskAbstract $task, int $seconds, callable $cb): void
    {
        $this->enqueue($task, function (TaskAbstract $task) use ($seconds, $cb) {
            $this->wait($task, $seconds, $cb);
        });
    }

    /**
     * @param TaskAbstract $task
     */
    abstract public function complete(TaskAbstract $task): void;

    /**
     * @param array|null $channels
     * @param callable $cb (TaskAbstract $task)
     */
    abstract public function poll(?array $channels, callable $cb): void;

    /**
     * @param callable $cb (array $channels)
     */
    abstract public function listChannels(callable $cb): void;

    /**
     * @param string $channel
     */
    abstract public function timedOutTasks(string $channel): void;
}
