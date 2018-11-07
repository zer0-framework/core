<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;
use Zer0\Cli\Controllers\Queue\Top;
use Zer0\Queue\SomeTask;
use Zer0\Queue\TaskAbstract;

/**
 * Class Queue
 * @package Zer0\Cli\Controllers
 */
final class Queue extends AbstractController
{
    use Top;

    /**
     * @var \Zer0\Queue\Pools\Base
     */
    protected $queue;

    /**
     * @var string
     */
    protected $command = 'queue';

    /**
     *
     */
    public function before(): void
    {
        parent::before();
        $this->queue = $this->app->broker('Queue')->get();
    }

    /**
     * @param string $channel
     */
    public function tapAction(string $channel = null, string $filter = null): void
    {
        $this->cli->interactiveMode(true);
        $this->queue->subscribe(
            $channel ?? 'default',
            function (?string $event, ?TaskAbstract $task) use ($filter): bool {
                static $styleSheet = [
                    'new' => 'fg(blue) i',
                    'complete' => 'fg(green) i',
                    'error' => 'fg(red) i',
                ];

                if ($event !== null) {
                    if ($filter !== null && $event !== $filter) {
                        return true;
                    }
                    if ($task->hasException()) {
                        $event = 'error';
                    }

                    $this->cli->write(strtoupper($event), $styleSheet[$event]);
                    $this->cli->write(str_repeat(' ', 15 - strlen($event)));

                    $this->cli->write($task->getId());
                    $this->cli->write("\t");

                    $this->cli->write(get_class($task));
                    $this->cli->write("\t");

                    $this->cli->colorfulJson($task);

                    $this->cli->writeln('');

                    if ($task->hasException()) {
                        $this->cli->writeln("⇧\t". $task->getException()->getMessage());
                    }
                }
                if (!$this->cli->interactiveMode()) {
                    return false;
                }
                return true;
            }
        );
        if (!$this->cli->interactiveMode()) {
            $this->cli->writeln('');
            return;
        }
    }

    /**
     *
     */
    public function testAction(): void
    {
        $this->cli->interactiveMode(true);
        for (; ;) {
            if (!$this->cli->interactiveMode()) {
                break;
            }
            $task = new SomeTask;
            $task->setChannel('test');
            $task->test = mt_rand(0, 10);
            $this->queue->enqueue($task);
            $this->cli->colorfulJson($task);
            $this->cli->writeln('');
            usleep(0.1 * 1e6);
        }
    }
}
