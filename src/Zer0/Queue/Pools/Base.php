<?php

namespace Zer0\Queue\Pools;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Queue\Exceptions\WaitTimeoutException;
use Zer0\Queue\TaskAbstract;

/**
 * Class Base
 * @package Zer0\Queue\Pools
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
     * @param TaskAbstract $task
     * @return TaskAbstract
     */
    abstract public function enqueue(TaskAbstract $task): TaskAbstract;

    /**
     * @param TaskAbstract $task
     * @param int $seconds
     * @return TaskAbstract
     * @throws WaitTimeoutException
     */
    public function enqueueWait(TaskAbstract $task, int $seconds = 3): TaskAbstract
    {
        $this->enqueue($task);
        return $this->wait($task, $seconds);
    }

    /**
     * @return int
     */
    abstract public function nextId(): int;

    /**
     * @param TaskAbstract $task
     * @param int $seconds
     * @return TaskAbstract
     * @throws WaitTimeoutException
     */
    abstract public function wait(TaskAbstract $task, int $seconds = 3): TaskAbstract;

    /**
     * @param TaskAbstract $task
     */
    abstract public function complete(TaskAbstract $task): void;

    /**
     * @param array|null $channels
     * @return null|TaskAbstract
     */
    abstract public function poll(?array $channels = null): ?TaskAbstract;

    /**
     * @return array
     */
    abstract public function listChannels(): array;

    /**
     * @param string $channel
     * @return
     */
    abstract public function timedOutTasks(string $channel);
}
