<?php

namespace Zer0\Cache\Pools;

use RedisClient\RedisClient;
use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Redis
 * @package Zer0\Cache\Pools
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
     * @var string
     */
    protected $tagPrefix;


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
        $this->prefix = $config->prefix ?? 'cache:';
        $this->tagPrefix = $config->tag_prefix ?? 'cache-tag:';
    }

    /**
     * @param string $key
     * @param bool &$hasValue
     * @return mixed|null
     */
    public function getValueByKey(string $key, &$hasValue = null)
    {
        $raw = $this->redis->get($this->prefix . $key);
        if ($raw === null) {
            $hasValue = false;
            return null;
        }
        try {
            $value = igbinary_unserialize($raw);
            $hasValue = true;
            return $value;
        } catch (\ErrorException $e) {
            $hasValue = false;
            return null;
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl Seconds to live
     * @return bool
     */
    public function saveKey(string $key, $value, int $ttl = 0): bool
    {
        return (bool)$this->redis->set($this->prefix . $key, igbinary_serialize($value), $ttl);
    }

    /**
     * @param Item $item
     * @return bool
     */
    public function invalidate(Item $item): bool
    {
        return (bool)$this->redis->del($this->prefix . $item->key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function invalidateKey(string $key): bool
    {
        return (bool)$this->redis->del($this->prefix . $key);
    }

    /**
     * @param string $tag
     * @return bool
     */
    public function invalidateTag(string $tag): bool
    {
        $keys = $this->redis->smembers($this->tagPrefix . $tag);
        $this->redis->multi();
        foreach ($keys as $key) {
            $this->redis->del($this->prefix . $key);
            $this->redis->srem($this->tagPrefix . $tag, $key);
        }
        $this->redis->exec();
        return true;
    }

    /**
     * @param Item $item
     * @return self
     */
    public function save(Item $item)
    {
        try {
            $this->saving = true;
            if (!$item->addTags && !$item->removeTags) {
                $this->saveKey($item->key, $item->value, $item->ttl);
            } else {
                $this->redis->multi();
                foreach ($item->addTags as $tag) {
                    $this->redis->sadd($this->tagPrefix . $tag, $item->key);
                }
                foreach ($item->removeTags as $tag) {
                    $this->redis->srem($this->tagPrefix . $tag, $item->key);
                }
                $this->saveKey($item->key, $item->value, $item->ttl);
                $this->redis->exec();
            }
            return $this;
        } finally {
            $this->saving = false;
        }
    }
}
