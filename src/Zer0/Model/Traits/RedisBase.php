<?php

namespace Zer0\Model\Traits;

use RedisClient\RedisClient;
use Zer0\App;

/**
 * Trait RedisBase
 * Common Redis methods used across storages and indexes.
 *
 * @package Zer0\Model\Traits
 */
trait RedisBase
{
    /** @var RedisClient Redis instance */
    protected $redis;

    protected $redisAsync;

    /** @var string $prefix Redis key prefix */
    protected $prefix;

    /** @var string $verSuffix Redis versioning key suffix */
    protected $verSuffix = ':ver';

    /**
     * Incremented in current transaction
     * [$key => true]
     * @var array
     */
    protected $verIncr = [];

    /** @var integer $ttl TTL in seconds */
    protected $ttl;

    /**
     * Is this affected by delayed() ?
     * @var boolean
     */
    protected $noDelay = true;

    /**
     * Use WATCH/INCR?
     * @var bool
     */
    protected $watch = false;

    /**
     * Redis shard name
     * @var string
     */
    protected $shard = '';

    /**
     * Constructor just loads redis instance from Shared
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->redis = App::instance()->broker('Redis')->get($this->shard);
        $this->redisAsync = App::instance()->broker('RedisAsync')->get($this->shard);
    }

    /**
     * Call Redis WATCH on keys. Redis EXEC will return false if keys values have changed in the
     * interval between the WATCH and EXEC calls.
     *
     * @param  array $ids IDs to watch
     * @return void
     */
    public function watch($ids)
    {
        if ($this->watch) {
            $keys = [];
            foreach ($ids as $id) {
                $keys[] = $this->prefix . $id . $this->verSuffix;
            }
            $this->redis->watch($keys);
        }
    }

    /**
     * Unwatch: cancel watching of all keys by our client.
     *
     * @return void
     */
    public function unwatch()
    {
        $this->redis->unwatch();
    }

    /**
     * Execute transaction
     * @return array
     */
    public function exec()
    {
        if (!$this->multi) {
            return [];
        }
        $this->multi = false;
        $this->verIncr = [];
        return $this->redis->exec();
    }

    /**
     * Begin MULTI transaction
     *
     * @param  boolean $atomic If true, this is an atomic transaction.
     *                         If false, this is just a pipeline (no guarantee of atomicity)
     * @return boolean|\Redis
     */
    public function multi($atomic = true)
    {
        if (!$this->multi) {
            return false;
        }
        $this->multi = true;
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        return $this->redis->multi($atomic ? \Redis::MULTI : \Redis::PIPELINE);
    }

    /**
     * Rollback transaction
     *
     * @return void
     */
    public function discard()
    {
        $this->multi = false;
        $this->verIncr = [];
        $this->redis->discard();
    }
}
