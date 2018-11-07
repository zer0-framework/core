<?php

namespace Zer0\Session\Storages;

use PHPDaemon\Clients\Redis\Pool;

/**
 * Class RedisAsync
 * @package Zer0\Session\Storages
 */
class RedisAsync extends BaseAsync
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
     * @var int
     */
    protected $ttl;


    /**
     *   Constructor
     */
    protected function init()
    {
        $this->redis = $this->app->broker('RedisAsync')->get();
        $this->prefix = $this->config->prefix;
        $this->ttl = $this->config->ttl ?? 60 * 60 * 24 * 31;
    }

    /**
     * @param string $id
     * @param callable $cb = null
     */
    public function read(string $id, $cb = null): void
    {
        $this->redis->hgetall($this->prefix . $id, function ($redis) use ($cb) {
            $cb($redis->arrayToHash($redis->result));
        });
    }

    /**
     * @param string $id
     * @param array $data
     * @param callable $cb = null
     * @return void
     */
    public function write(string $id, array $data, $cb = null): void
    {
        $this->redis->multi(function ($redis) use ($id, $data, $cb) {
            $redisKey = $this->prefix . $id;
            $redis->hmset($redisKey, $data)->exec();
            $redis->setTimeout($redisKey, $this->ttl);
            $redis->exec(function ($redis) use ($cb) {
                if ($cb !== null) {
                    $cb(true);
                }
            });
        });
    }

    /**
     * @param string $id
     * @param array $transaction
     * @param callable $cb = null
     * @return void
     */
    public function transaction(string $id, array $transaction, $cb = null): void
    {
        $this->redis->multi(function ($redis) use ($id, $transaction, $cb) {
            $redisKey = $this->prefix . $id;
            if (isset($transaction['$set'])) {
                $redis->hmset($redisKey, $transaction['$set']);
            }
            if (isset($transaction['$unset'])) {
                $redis->hDel($redisKey, ...array_keys($transaction['$unset']));
            }
            if (isset($transaction['$incr'])) {
                foreach ($transaction['$incr'] as $key => $value) {
                    $redis->hIncrBy($redisKey, $key, $value);
                }
            }
            $redis->exec(function ($redis) use ($cb) {
                if ($cb !== null) {
                    $cb(true);
                }
            });
        });
    }

    /**
     * @param string $id
     * @param callable $cb = null
     * @return void
     */
    public function destroy(string $id, $cb = null): void
    {
        $this->redis->del($this->prefix . $id, function ($redis) use ($cb) {
            if ($cb !== null) {
                $cb(true);
            }
        });
    }

    /**
     * @param string $old
     * @param string $new
     * @param callable $cb = null
     * @return void
     */
    public function rename(string $old, string $new, $cb = null): void
    {
        $this->redis->rename($this->prefix . $old, $this->prefix . $new, function ($redis) use ($cb) {
            if ($cb !== null) {
                $cb(true);
            }
        });
    }
}
