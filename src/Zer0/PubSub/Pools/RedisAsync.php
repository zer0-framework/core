<?php

namespace Zer0\PubSub\Pools;

use PHPDaemon\Clients\Redis\Connection as RedisConnection;
use PHPDaemon\Clients\Redis\Pool;
use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\PubSub\MessageInterface;

/**
 * Class RedisAsync
 * @package Zer0\PubSub\Pools
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
     * Holds current subscriptions with callbacks
     * [channelName => [callback0, callback1, ...], ...]
     * @var array
     */
    protected $subscribed = [];

    /**
     * Holds current subscriptions with callbacks
     * [channelName => [callback0, callback1, ...], ...]
     * @var array
     */
    protected $subscribedPatterns = [];

    /**
     * Redis constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        parent::__construct($config, $app);
        $this->redis = $this->app->broker('RedisAsync')->get($config->name ?? '');
        $this->prefix = $config->prefix ?? 'pubsub';
    }

    /**
     * @param MessageInterface $message
     * @param callable|null $cb
     */
    public function publish(MessageInterface $message, ?callable $cb = null): void
    {
        $this->redis->publish(
            $this->prefix . ':' . $message->channel,
            igbinary_serialize($message),
            function ($redis) use ($cb) {
                if ($cb !== null) {
                    $cb($redis->result);
                }
            }
        );
    }

    /**
     *
     */
    public function unsubscribeAll(): void
    {
        $this->redis->unsubscribe(array_keys($this->subscribed), [$this, '_subscriber']);
        $this->subscribed = [];
    }

    /**
     * @param array $channels
     */
    public function unsubscribe($channels): void
    {
        $channels = (array)$channels;
        foreach ($channels as $chan) {
            if (!isset($this->subscribed[$chan])) {
                return;
            }
            unset($this->subscribed[$chan]);
            $removeChannels[] = $this->prefix . ':' . $chan;
        }
        if (count($removeChannels)) {
            $this->redis->unsubscribe($removeChannels, [$this, '_subscriber']);
        }
    }


    /**
     * @param array $patterns
     * @param callable $cb
     */
    public function psubscribe($patterns, callable $cb): void
    {
        $patterns = (array)$patterns;
        $addPatterns = [];
        foreach ($patterns as $pattern) {
            $ref =& $this->subscribedPatterns[$pattern];
            if ($ref === null) {
                $ref = [];
                $addPatterns[] = $this->prefix . ':' . $pattern;
            }
            $ref[] = $cb;
        }
        if ($addPatterns) {
            $this->redis->psubscribe($addPatterns, [$this, '_psubscriber']);
        }
    }

    /**
     * @param array $channels
     * @param callable $cb
     */
    public function subscribe($channels, callable $cb): void
    {
        $channels = (array)$channels;
        $addChannels = [];
        foreach ($channels as $chan) {
            $ref =& $this->subscribed[$chan];
            if ($ref === null) {
                $ref = null;
                $addChannels[] = $this->prefix . ':' . $chan;
            }
            $ref[] = $cb;
        }
        if ($addChannels) {
            $this->redis->subscribe($addChannels, [$this, '_subscriber']);
        }
    }

    /**
     * Callback for Redis subscription
     *
     * @param RedisConnection $redis
     */
    public function _subscriber(RedisConnection $redis)
    {
        if (!$redis) {
            return;
        }
        list(, , $serialized) = $redis->result;
        try {
            $message = igbinary_unserialize($serialized);
            $chan = $message->channel;
            foreach ($this->subscribed[$chan] ?? [] as $cb) {
                $cb($message->payload, $chan);
            }
        } catch (\ErrorException $e) {
        }
    }

    /**
     * Callback for Redis subscription
     *
     * @param RedisConnection $redis
     */
    public function _psubscriber(RedisConnection $redis)
    {
        if (!$redis) {
            return;
        }
        list(, $pattern, , $serialized) = $redis->result;
        $pattern = substr($pattern, strlen($this->prefix) + 1);
        try {
            $message = igbinary_unserialize($serialized);
            $chan = $message->channel;
            foreach ($this->subscribedPatterns[$pattern] ?? [] as $cb) {
                $cb($message->payload, $chan);
            }
        } catch (\ErrorException $e) {
        }
    }
}
