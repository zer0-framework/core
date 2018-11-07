<?php

namespace Zer0\Queue\Pools;

use RedisClient\Pipeline\PipelineInterface;
use RedisClient\RedisClient;
use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Queue\Exceptions\IncorrectStateException;
use Zer0\Queue\Exceptions\WaitTimeoutException;
use Zer0\Queue\TaskAbstract;

/**
 * Class Redis
 * @package Zer0\Queue\Pools
 */
final class Redis extends Base
{

    /**
     * @var RedisClient
     */
    protected $redis;

    /**
     * @var RedisClient
     */
    protected $pubSubRedis;

    /**
     * @var string
     */
    protected $prefix;


    /**
     * @var bool
     */
    protected $saving = false;

    /**
     * Redis constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        parent::__construct($config, $app);
        $this->redis = $this->app->broker('Redis')->get($config->redis ?? '');
        $this->prefix = $config->prefix ?? 'queue';
    }

    /**
     * @param TaskAbstract $task
     * @return TaskAbstract
     * @throws \RedisClient\Exception\InvalidArgumentException
     */
    public function enqueue(TaskAbstract $task): TaskAbstract
    {
        $taskId = $task->getId();
        $autoId = $taskId === null;
        if ($autoId) {
            $task->setId($taskId = $this->nextId());
        }
        $channel = $task->getChannel();

        $payload = igbinary_serialize($task);

        $pipeline = function (PipelineInterface $redis) use (
            $taskId,
            $task,
            $payload,
            $channel,
            $autoId
        ) {
            $redis->publish($this->prefix . ':enqueue-channel:' . $channel, $payload);
            $redis->multi();
            $redis->sAdd($this->prefix . ':list-channels', $channel);
            $redis->rPush($this->prefix . ':channel:' . $channel, $taskId);
            $redis->incr($this->prefix . ':channel-total:' . $channel);
            $redis->set($this->prefix . ':input:' . $taskId, $payload);
            $redis->del([
                $this->prefix . ':output:' . $taskId,
                $this->prefix . ':blpop:' . $taskId
            ]);

            $redis->exec();
        };

        if (!$autoId && $task->getTimeoutSeconds() > 0) {
            if ($this->redis->zAdd(
                $this->prefix . ':channel-pending:' . $channel,
                [$taskId . ':' . $task->getTimeoutSeconds() => time() + $task->getTimeoutSeconds()],
                'NX')) {
                $this->redis->pipeline($pipeline);
            }
        } else {
            $this->redis->pipeline($pipeline);
        }

        return $task;
    }

    /**
     * @param string $channel
     * @param callable $cb
     * @throws \RedisClient\Exception\InvalidArgumentException
     */
    public function subscribe(string $channel, callable $cb): void
    {
        if ($this->pubSubRedis === null) {
            $broker = $this->app->broker('Redis');
            $config = clone $broker->getConfig();
            $config->timeout = 0.01;
            $this->pubSubRedis = $broker->instantiate($config);
        }
        $this->pubSubRedis->subscribe([
            $this->prefix . ':enqueue-channel:' . $channel,
            $this->prefix . ':complete-channel:' . $channel,
        ], function ($type, $chan, $data) use ($cb, $channel) {
            $event = null;
            if ($type === 'message') {
                list($type, $eventChannel) = explode(':', substr($chan, strlen($this->prefix . ':')));
                if ($channel !== $eventChannel) {
                    $event = null;
                } elseif ($type === 'enqueue-channel') {
                    $event = 'new';
                } elseif ($type === 'complete-channel') {
                    $event = 'complete';
                }
                try {
                    $data = igbinary_unserialize($data);
                } catch (\ErrorException $e) {
                    $event = null;
                }
            }
            return $cb($event, $event !== null ? $data : null);
        });
    }

    /**
     * @return int
     */
    public function nextId(): int
    {
        return $this->redis->incr($this->prefix . ':task-seq');
    }

    /**
     * @param TaskAbstract $task
     * @param int $timeout
     * @return TaskAbstract
     * @throws IncorrectStateException
     * @throws WaitTimeoutException
     */
    public function wait(TaskAbstract $task, int $timeout = 3): TaskAbstract
    {
        $taskId = $task->getId();
        if (!$this->redis->blpop([$this->prefix . ':blpop:' . $taskId], $timeout)) {
            throw new WaitTimeoutException;
        }

        $payload = $this->redis->get($this->prefix . ':output:' . $taskId);
        if ($payload === false) {
            throw new IncorrectStateException;
        }

        return igbinary_unserialize($payload);
    }

    /**
     * @param array|null $channels
     * @return null|TaskAbstract
     */
    public function poll(?array $channels = null): ?TaskAbstract
    {
        if ($channels === null) {
            $channels = $this->listChannels();
        }
        $prefix = $this->prefix . ':channel:';
        $keys = [];
        foreach ($channels as $chan) {
            $keys[] = $prefix . $chan;
        }

        $reply = $this->redis->blpop($keys, 1);
        if (!$reply) {
            return null;
        }

        foreach ($reply as $key => $taskId) {
            break;
        }
        $channel = substr($key, strlen($prefix));
        $payload = $this->redis->get($this->prefix . ':input:' . $taskId);
        try {
            $task = igbinary_unserialize($payload);
        } catch (\ErrorException $e) {
            return null;
        }
        $task->setChannel($channel);

        return $task;
    }

    /**
     * @return array
     */
    public function listChannels(): array
    {
        return $this->redis->smembers($this->prefix . ':list-channels');
    }

    /**
     * @param string $channel
     * @param int $start
     * @param int $stop
     * @return array
     */
    public function pendingTasks(string $channel, int $start = 0, int $stop = -1): array
    {
        $items = $this->redis->zrange(
            $this->prefix . ':channel-pending:' . $channel,
            $start,
            $stop
        );
        $keys = [];
        foreach ($items as $value) {
            list($taskId, $timeout) = explode(':', $value);
            $keys[] = $this->prefix . ':input:' . $taskId;
        }
        if (!$keys) {
            return [];
        }
        $mget = $this->redis->mget($keys);
        $ret = [];
        foreach ($mget as $key => $item) {
            if (!is_string($item)) {
                continue;
            }
            try {
                $ret[] = igbinary_unserialize($item);
            } catch (\ErrorException $e) {
            }
        }
        return $ret;
    }

    /**
     * @param string $channel
     * @return array
     * @throws \RedisClient\Exception\InvalidArgumentException
     */
    public function getChannelStats(string $channel): array
    {
        $res = $this->redis->pipeline(function (PipelineInterface $pipeline) use ($channel) {
            $pipeline->multi();
            $pipeline->get($this->prefix . ':channel-total:' . $channel); // Total
            $pipeline->llen($this->prefix . ':channel:' . $channel);   // Backlog
            $pipeline->zcard($this->prefix . ':channel-pending:' . $channel); // Pending
            $pipeline->exec();
        });
        $res = $res[4];
        $stats = [
            'total' => (int)$res[0],
            'backlog' => $res[1],
            'pending' => min($res[2], (int)$res[0]),
        ];
        $stats['complete'] = $stats['total'] - $stats['pending'];
        return $stats;
    }

    /**
     * @param string $channel
     */
    public function timedOutTasks(string $channel): void
    {
        $zset = $this->prefix . ':channel-pending:' . $channel;
        $redis = $this->redis;
        $redis->watch($zset);
        $res = $redis->zrangebyscore($zset, '-inf', time(), ['limit' => [0, 1]]);
        $keys = [];
        foreach ($res as $value) {
            list($taskId, $timeout) = explode(':', $value, 2);
            $keys[] = $this->prefix . ':input:' . $taskId;
        }
        $mget = $redis->mget($keys);
        foreach ($res as $value) {
            list($taskId, $timeout) = explode(':', $value, 2);
            if ($timeout === '0') {
                continue;
            }
            $redis->multi();
            $redis->zAdd($zset, [$value => time() + $timeout]);
            $redis->sadd($this->prefix . ':list-channels', $channel);
            $redis->rpush($this->prefix . ':channel:' . $channel, $taskId);
            $redis->exec();
        }
        $this->timedOutTasks($channel);
    }

    /**
     * @param TaskAbstract $task
     * @throws \RedisClient\Exception\InvalidArgumentException
     */
    public function complete(TaskAbstract $task): void
    {
        $this->redis->pipeline(function (PipelineInterface $redis) use ($task) {
            $redis->multi();

            $taskId = $task->getId();

            $payload = igbinary_serialize($task);

            $redis->publish($this->prefix . ':output:' . $taskId, $payload);

            $channel = $task->getChannel();

            $redis->publish($this->prefix . ':complete-channel:' . $channel, $payload);

            if ($task->getTimeoutSeconds() > 0) {
                $this->redis->zrem(
                    $this->prefix . ':channel-pending:' . $channel,
                    [
                        $task->getId() . ':' . $task->getTimeoutSeconds()
                    ]
                );
            }

            $redis->set($this->prefix . ':output:' . $taskId, $payload);
            $redis->expire($this->prefix . ':output:' . $taskId, 15 * 60);

            $redis->rPush($this->prefix . ':blpop:' . $taskId, range(1, 10));
            $redis->expire($this->prefix . ':blpop:' . $taskId, 10);

            $redis->del($this->prefix . ':input:' . $taskId);

            $redis->exec();
        });
    }
}
