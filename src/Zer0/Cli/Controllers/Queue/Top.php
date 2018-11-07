<?php

namespace Zer0\Cli\Controllers\Queue;

use Hoa\Console\Cursor;
use Hoa\Console\Readline\Readline;
use Hoa\Console\Window;
use Zer0\Cli\Helpers\RefreshableWindow;

/**
 * Trait Top
 * @package Zer0\Cli\Controllers\Queue
 */
trait Top
{

    /**
     * @var int
     */
    protected $channelTabIndex = 0;

    /**
     * @var array
     */
    protected $channels = [];

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var string
     */
    protected $subscribedChannel;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var array
     */
    protected $queueEventHistory = [];

    /**
     * @var RefreshableWindow
     */
    protected $window;

    /**
     * @param int|null $pos
     */
    protected function tabChannel(int $pos = null)
    {
        if ($pos === null) {
            ++$this->channelTabIndex;
        } else {
            $this->channelTabIndex = $pos;
        }
        $this->channels = $this->queue->listChannels();
        $this->channelTabIndex = $this->channels ? $this->channelTabIndex % count($this->channels) : 0;
        $this->channel = $this->channels[$this->channelTabIndex] ?? null;
    }

    /**
     * @param string $channel
     */
    public function monitorAction(string $channel = 'default'): void
    {
        $this->window('monitor', $channel);
    }

    /**
     * @param string $channel
     */
    public function topAction(string $channel = 'default'): void
    {
        $this->window('top', $channel);
    }

    /**
     * @param string $mode
     * @param string $channel
     */
    public function window(string $mode = 'top', string $channel = 'default'): void
    {
        $this->mode = $mode;
        $rl = new class extends Readline {
            /**
             * @param $method
             * @param $args
             * @return mixed
             */
            /**
             * @param $method
             * @param $args
             * @return mixed
             */
            /**
             * @param $method
             * @param $args
             * @return mixed
             */
            public function __call($method, $args)
            {
                return $this->{$method}(...$args);
            }
        };
        $rl->addMapping("\t", function (Readline $rl) {
            $this->tabChannel();
            return Readline::STATE_BREAK;
        });

        $rl->addMapping('t', function (Readline $rl) {
            $this->mode = 'top';
            return Readline::STATE_BREAK;
        });
        $rl->addMapping('m', function (Readline $rl) {
            $this->mode = 'monitor';
            return Readline::STATE_BREAK;
        });

        $this->cli->interactiveMode(true);

        $this->window = new RefreshableWindow;
        Cursor::clear('all');

        $draw = function () {
            Cursor::clear('line');
            echo PHP_EOL;
            Cursor::clear('all');
            $windowSize = Window::getSize();

            if ($this->channel === null) {
                echo "No channels 🧐" . PHP_EOL;
                echo PHP_EOL;
                echo "Press 'q' to exit" . PHP_EOL;
                $this->window->refresh();
                return;
            }

            $fps = $this->window->getFps();
            $fps = $fps !== null ? round($fps) : '~';
            echo 'FPS: ' . $fps . PHP_EOL;
            echo "Mode:\t";
            foreach (['top', 'monitor'] as $item) {
                echo "\t";
                if ($item === $this->mode) {
                    $this->cli->write($item, 'inverse');
                } else {
                    $this->cli->write('[' . substr($item, 0, 1) . ']' . substr($item, 1));
                }
            }
            echo PHP_EOL;
            echo 'Channels:';
            foreach ($this->channels as $i => $item) {
                echo "\t";
                $this->cli->write($item, $item === $this->channel ? 'inverse' : null);
            }
            echo PHP_EOL;

            echo PHP_EOL;

            $stats = $this->queue->getChannelStats($this->channel);

            echo 'Backlog: ' . number_format($stats['backlog']) . "\t";
            echo 'Pending: ' . number_format($stats['pending']) . "\t";
            echo 'Total: ' . number_format($stats['total']) . "\t";
            echo 'Complete: ' . number_format($stats['complete']) . "\t";
            echo PHP_EOL;
            echo PHP_EOL;

            require_once ZERO_ROOT . '/vendor/kakserpom/quicky/plugins/modifier.mb_truncate.php';
            if ($this->mode === 'monitor') {
                foreach (array_slice($this->queueEventHistory, 0, $windowSize['y'] - 10) as $item) {
                    list($event, $data) = $item;
                    if ($event === 'new') {
                        $task = $data;
                        echo($pre = 'NEW: ' . get_class($task) . ': ')
                            . quicky_modifier_mb_truncate(json_encode($task), $windowSize['x'] - strlen($pre)) . PHP_EOL;
                    } elseif ($event === 'complete') {
                        $task = $data;
                        echo($pre = 'COMPLETE: ' . get_class($task) . ': ')
                            . quicky_modifier_mb_truncate(json_encode($task), $windowSize['x'] - strlen($pre)) . PHP_EOL;
                    }
                }
            } else {
                $tasks = $this->queue->pendingTasks($this->channel, 0, $windowSize['y'] - 10);
                foreach ($tasks as $task) {
                    echo($pre = get_class($task) . ': ')
                        . quicky_modifier_mb_truncate(json_encode($task), $windowSize['x'] - strlen($pre)) . PHP_EOL;
                }
            }
            $this->window->refresh();
        };

        $rl->setFrameCallback(function (Readline $rl) use ($draw) {
            if (!$this->cli->interactiveMode()) {
                return true;
            }
            $draw();
            Cursor::clear('line');
            $line = $rl->getLine();
            /** @noinspection Annotator */
            $rl->resetLine();
            if ($line === 'q') {
                $this->cli->interactiveMode(false);
                return true;
            }
            if ($this->mode === 'monitor') {
                return true; // immediate break to pull new data from queue
            }
            return false;
        });

        $this->channels = $this->queue->listChannels();
        $tabIndex = array_search($channel, $this->channels);

        $this->tabChannel($tabIndex !== false ? $tabIndex : 0);

        $draw();

        start:
        if ($this->mode === 'monitor') {
            $rl->setSelectTimeout(0.15);
            $this->subscribedChannel = $this->channel;
            $this->queueEventHistory = [];
            $this->queue->subscribe(
                $this->channel ?? 'default',
                function (?string $event, $data) use ($rl, $draw): bool {
                    if ($event !== null) {
                        array_unshift($this->queueEventHistory, [$event, $data]);
                        $draw();
                    }
                    if ($this->mode !== 'monitor') {
                        return false;
                    }
                    if ($this->channel !== $this->subscribedChannel) {
                        return false;
                    }
                    if (!$this->cli->interactiveMode()) {
                        $this->window->destroy();
                        return false;
                    }
                    $rl->readLine();
                    return true;
                }
            );
            if (!$this->cli->interactiveMode()) {
                return;
            }
            goto start;
        } elseif ($this->mode === 'top') {
            $rl->setSelectTimeout(0.5);

            for (; ;) {
                $rl->readLine();
                if ($this->mode !== 'top') {
                    goto start;
                }
                if (!$this->cli->interactiveMode()) {
                    $this->window->destroy();
                    return;
                }
            }
        } else {
            echo 'Wrong mode.' . PHP_EOL;
        }
    }
}
