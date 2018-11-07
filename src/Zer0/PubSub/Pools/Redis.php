<?php

namespace Zer0\PubSub\Pools;

use RedisClient\RedisClient;
use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\PubSub\Message;

/**
 * Class Redis
 * @package Zer0\PubSub\Pools
 */
final class Redis extends Base
{

    /**
     * @var RedisClient
     */
    protected $redis;


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
        $this->prefix = $config->prefix ?? 'pubsub';
    }

    /**
     * @param Message $message
     * @return int
     */
    public function publish(Message $message): int
    {
        return $this->redis->publish(
            $this->prefix . ':' . $message->channel,
            igbinary_serialize($message)
        );
    }
}
