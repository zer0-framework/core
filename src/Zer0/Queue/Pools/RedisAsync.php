<?php

namespace Zer0\Queue\Pools;

use PHPDaemon\Clients\Redis\Connection as RedisConnection;
use PHPDaemon\Clients\Redis\Pool;
use PHPDaemon\Core\Daemon;
use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Queue\Exceptions\IncorrectStateException;
use Zer0\Queue\TaskAbstract;

/**
 * Class RedisAsync
 * @package Zer0\Queue\Pools
 */
final class RedisAsync extends BaseAsync
{

    /**
     * @var Pool
     */
    protected $redis;


    /**
     * @var string
     */
    protected $prefix;

    /**
     * Redis constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        parent::__construct($config, $app);
        $this->redis = $this->app->broker('RedisAsync')->get($config->name ?? '');
        $this->prefix = $config->prefix ?? 'queue';
    }

    /**
     * @param TaskAbstract $task
     * @param callable $cb (?TaskAbstract $task, BaseAsync $pool)
     */
    public function enqueue(TaskAbstract $task, ?callable $cb = null): void
    {
        $taskId = $task->getId();
        if ($taskId === null) {
            $this->nextId(function ($id) use ($task, $cb) {
                $task->setId($id);
                $this->enqueue($task, $cb);
            });
            return;
        }
        $autoId = ctype_digit($taskId);

        $channel = $task->getChannel();

        $payload = igbinary_serialize($task);
        $func = function ($redis) use ($cb, $task, $channel, $taskId, $autoId, $payload) {
            if ($task->getTimeoutSeconds() > 0) {
                if (!$redis->result) {
                    if ($cb !== null) {
                        $cb(null, $this);
                    }
                    return;
                }
            }
            $redis->publish($this->prefix . ':enqueue-channel:' . $channel, $payload);
            $redis->multi();
            $redis->sAdd($this->prefix . ':list-channels', $channel);
            $redis->rPush($this->prefix . ':channel:' . $channel, $taskId);
            $redis->incr($this->prefix . ':channel-total:' . $channel);
            $redis->set($this->prefix . ':input:' . $taskId, $payload);
            $redis->del($this->prefix . ':output:' . $taskId, $this->prefix . ':blpop:' . $taskId);
            $redis->exec(function (RedisConnection $redis) use ($cb, $task) {
                if ($cb !== null) {
                    $cb($task, $this);
                }
            });
        };

        if ($task->getTimeoutSeconds() > 0) {
            $this->redis->zadd(
                'zadd',
                $this->prefix . ':channel-pending:' . $channel,
                'NX',
                time() + $task->getTimeoutSeconds(),
                $taskId . ':' . $task->getTimeoutSeconds(),
                $func
            );
        } else {
            $func($this->redis);
        }
    }

    /**
     * @param callable $cb (int $id)
     */
    public function nextId(callable $cb): void
    {
        $this->redis->incr($this->prefix . ':task-seq', function (RedisConnection $redis) use ($cb) {
            $cb($redis->result);
        });
    }

    /**
     * @param TaskAbstract $task
     * @param int $seconds
     * @param callable $cb (?TaskAbstract $task)
     * @throws IncorrectStateException
     */
    public function wait(TaskAbstract $task, int $seconds, callable $cb): void
    {
        $taskId = $task->getId();
        if ($taskId === null) {
            throw new IncorrectStateException;
        }
        $this->redis->blPop(
            $this->prefix . ':blpop:' . $taskId,
            $seconds,
            function (RedisConnection $redis) use ($taskId, $cb) {
                if (!$redis->result) {
                    $cb(null);
                    return;
                }
                $this->redis->get($this->prefix . ':output:' . $taskId, function (RedisConnection $redis) use ($cb) {
                    if ($redis->result === null) {
                        $cb(null);
                    }
                    try {
                        $cb(igbinary_unserialize($redis->result));
                    } catch (\ErrorException $e) {
                        $cb(null);
                    }
                });
            }
        );
    }

    /**
     * @param array|null $channels
     * @param callable $cb (TaskAbstract $task)
     */
    public function poll(?array $channels = null, callable $cb): void
    {
        if ($channels === null) {
            $this->listChannels(function (?array $channels) use ($cb) {
                if (!$channels) {
                    $cb(null);
                    return;
                }
                $this->poll($channels, $cb);
            });
            return;
        }
        $keys = [];
        foreach ($channels as $chan) {
            $keys[] = $this->prefix . ':channel:' . $chan;
        }

        $this->redis->blPop(...array_merge($keys, [
            1,
            function (RedisConnection $redis) use ($cb) {
                if (!$redis->result) {
                    $cb(null);
                    return;
                }
                list($key, $taskId) = $redis->result;
                $channel = substr($key, strlen($this->prefix . ':channel:'));

                $this->redis->get(
                    $this->prefix . ':input:' . $taskId,
                    function (RedisConnection $redis) use ($channel, $cb) {
                        if (!$redis->result) {
                            $cb(null);
                            return;
                        }
                        $task = igbinary_unserialize($redis->result);
                        $task->setChannel($channel);
                        $cb($task);
                    }
                );
            }
        ]));
    }

    /**
     * @param callable $cb (?array $channels)
     */
    public function listChannels(callable $cb): void
    {
        $this->redis->sMembers($this->prefix . ':list-channels', function (RedisConnection $redis) use ($cb) {
            $cb((array)$redis->result);
        });
        return;
    }

    /**
     * @param string $channel
     */
    public function timedOutTasks(string $channel): void
    {
        $zset = $this->prefix . ':channel-pending:' . $channel;
        $this->redis->watch($zset, function (RedisConnection $redis) use ($zset, $channel) {
            $redis->zRangeByScore(
                $zset,
                '-inf',
                time(),
                'LIMIT',
                0,
                5,
                function (RedisConnection $redis) use ($zset, $channel) {
                    if (!$redis->result) {
                        return;
                    }
                    if (!is_array($redis->result)) {
                        Daemon::$process->log((string)new \Exception(
                            'Expected array, given: ' . var_export($redis->result, true)
                        ));
                        return;
                    }
                    $zrange = $redis->result;
                    $args = [];
                    foreach ($zrange as $value) {
                        list($taskId, $timeout) = explode(':', $value, 2);
                        $args[] = $this->prefix . ':input:' . $taskId;
                    }
                    $args[] = function ($redis) use ($zset, $zrange, $channel) {
                        $redis->multi();
                        foreach ($zrange as $i => $value) {
                            if ($redis->result[$i] === null) {
                                $redis->zRem($zset, $value);
                            } else {
                                list($taskId, $timeout) = explode(':', $value, 2);

                                if ($timeout === '0') {
                                    continue;
                                }
                                $redis->zAdd($zset, time() + $timeout, $value);
                                $redis->sAdd($this->prefix . ':list-channels', $channel);
                                $redis->rPush($this->prefix . ':channel:' . $channel, $taskId);
                            }
                        }
                        $redis->exec(function (RedisConnection $redis) use ($channel) {
                            $this->timedOutTasks($channel);
                        });
                    };
                    $redis->mget(...$args);
                }
            );
        });
    }

    /**
     * @param TaskAbstract $task
     */
    public function complete(TaskAbstract $task): void
    {
        $payload = igbinary_serialize($task);
        $this->redis->multi(function (RedisConnection $redis) use ($task, $payload) {
            $taskId = $task->getId();
            $redis->publish($this->prefix . ':output:' . $taskId, $payload);

            $channel = $task->getChannel();
            $redis->publish($this->prefix . ':complete-channel:' . $channel, $payload);
            $redis->zRem(
                $this->prefix . ':channel-pending:' . $channel,
                $task->getId() . ':' . $task->getTimeoutSeconds()
            );
            $redis->set($this->prefix . ':output:' . $taskId, $payload);
            $redis->expire($this->prefix . ':output:' . $taskId, 15 * 60);

            $redis->rPush($this->prefix . ':blpop:' . $taskId, ...range(1, 10));
            $redis->expire($this->prefix . ':blpop:' . $taskId, 10);

            $redis->del($this->prefix . ':input:' . $taskId);

            $redis->exec();
        });
    }
}
