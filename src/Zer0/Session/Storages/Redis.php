<?php

namespace Zer0\Session\Storages;

use RedisClient\RedisClient;

/**
 * Class Redis
 * @package Zer0\Session\Storages
 */
class Redis extends Base
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
     * @var int
     */
    protected $ttl;


    /**
     *   Constructor
     */
    protected function init()
    {
        $this->redis = $this->app->broker('Redis')->get();
        $this->prefix = $this->config->prefix;
        $this->ttl = $this->config->ttl ?? 60 * 60 * 24 * 31;
    }

    /**
     * @param string $id
     * @return array
     */
    public function read(string $id): array
    {
        return $this->redis->hgetall($this->prefix . $id);
    }

    /**
     * @param string $id
     * @param array $data
     * @return void
     */
    public function write(string $id, array $data): void
    {
        $redisKey = $this->prefix . $id;
        $redis = $this->redis;
        $redis->multi();
        $redis->hmset($redisKey, $data)->exec();
        $redis->setTimeout($redisKey, $this->ttl);
        $redis->exec();
    }

    /**
     * @param string $id
     * @param array $transaction
     * @return void
     */
    public function transaction(string $id, array $transaction): void
    {
        $redisKey = $this->prefix . $id;

        $redis = $this->redis;
        $multi = false;
        if (isset($transaction['$set'])) {
            if (!$multi) {
                $redis->multi();
                $multi = true;
            }
            $redis->hmset($redisKey, $transaction['$set']);
        }
        if (isset($transaction['$unset'])) {
            if (!$multi) {
                $redis->multi();
                $multi = true;
            }
            $redis->hdel($redisKey, ...array_keys($transaction['$unset']));
        }
        if (isset($transaction['$incr'])) {
            if (!$multi) {
                $redis->multi();
                $multi = true;
            }
            foreach ($transaction['$incr'] as $key => $value) {
                $redis->hincrby($redisKey, $key, $value);
            }
        }
        if ($multi) {
            $redis->exec();
        }
    }

    /**
     * @param string $id
     * @return void
     */
    public function destroy(string $id): void
    {
        $this->redis->del($this->prefix . $id);
    }

    /**
     * @param string $old
     * @param string $new
     * @return void
     */
    public function rename(string $old, string $new): void
    {
        $this->redis->rename($this->prefix . $old, $this->prefix . $new);
    }
}
