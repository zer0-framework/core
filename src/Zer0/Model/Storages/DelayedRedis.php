<?php

namespace Zer0\Model\Storages;

use Zer0\Model\Expressions\Conditions\Generic as GenericCond;

/**
 * Class DelayedRedis
 *
 * This storage type places operations on data in a queue for batch processing at a given interval.
 * If a non-delayed operation occurs on the same data before the delayed operation, this cancels the delayed
 * operation on the affected fields to avoid overwriting with stale data. Hence Delayed should be used
 * only on non-critical data operations that can be lost (e.g. activity timestamps, etc.).
 *
 * If multiple operations share the same model class, they will be executed in a single begin/commit block,
 * but if they are different, they can be executed in random order.
 *
 * If a non-delayed operation occurs on the same data before the delayed operation, this cancels the delayed
 * operation on the affected fields to avoid overwriting with stale data.
 *
 * @package Zer0\Model\Storages
 */
class DelayedRedis extends Generic
{
    use \Zer0\Model\Traits\RedisBase;

    /**
     * Prefix for Redis hashes
     * @var string
     */
    const HASH_PREFIX = 'delayed:';

    /**
     * Prefix for Redis sets (batches)
     * @var string
     */
    const BATCH_PREFIX = 'delayed-batch-';

    /** @var string $primaryKey */
    protected $primaryKey;

    /** @var int $maxDelay Maximum interval before operation is executed (in seconds) */
    protected $maxDelay = 1;

    /**
     * Redis shard name
     * @var string
     */
    protected $shardDefault = 'queue';

    /**
     * 'purge': Prevent old changes from overriding new changes, 'normal': Write delayed changes.
     *
     * @var string
     */
    protected $mode = 'purge';

    /**
     * Push new object into storage. Takes a $data array from which we generate a string containing
     * only the field values specified in the storages config array (the values of the 'value' key).
     * Score field(s) are also determined by the storages config array ('score' values), and
     * converted to string from $data.
     *
     * @param  array $data Field => value array with object data
     * @param  boolean $upsertMode = false
     * @param  callable $cb = null Callback
     * @return void If array, result of redis->exec()
     * @callback $cb ()
     */
    public function create($data, $upsertMode = false, $cb = null)
    {
        // Generate operation with data to be set
        $key = static::HASH_PREFIX . $this->prefix . $data[$this->primaryKey];
        $transactionStarted = $this->multi();
        $this->redis->del($key);
        $hmset = [];
        foreach ($data as $field => $value) {
            if ($value === null) {
                continue;
            }
            $hmset['$set.' . $field] = $value;
        }
        $hmset['$create'] = '1';
        $this->redis->hmset($key, $hmset);

        // Add to batch (set) of operations to be executed
        $this->redis->sadd(static::BATCH_PREFIX . $this->maxDelay . ':' . $this->modelClass, $key);
        if ($cb !== null) {
            $cb();
        }

        if ($transactionStarted) {
            $this->exec();
        }
    }

    /**
     * Deletes objects
     *
     * @param \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  callable $cb = null Callback
     * @return integer Number of deleted objects
     * @throws \Exception
     * @callback $cb (integer $affectedRows)
     *
     */
    public function delete(GenericCond $where, $cb = null)
    {
        if ($this->mode === 'purge') {
            return (int)$this->update($where, ['$delete' => '1'], $cb);
        } else {
            return $this->update($where, ['$delete' => '1'], $cb);
        }
    }

    /**
     * Save a 'transaction' in Redis to be executed. Performs set, increment and unset operations as
     * specified in $updatePlan across Redis hash objects matching $where condition.
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  array $updatePlan Update plan — https://docs.mongodb.org/manual/reference/operator/update/#id1
     * @param  callable $cb = null Callback
     * @return int Affected
     * @throws \Exception
     * @callback $cb (boolean $success)
     *
     */
    public function update(GenericCond $where, $updatePlan, $cb = null)
    {
        if ($this->mode === 'purge') {
            return $this->updatePurge($where, $updatePlan, $cb);
        }

        $prefix = static::HASH_PREFIX . $this->prefix;
        if ($cb !== null) {
            $cb(0);
        }
        $ids = $where->getIds();
        if (!count($updatePlan)) {
            return 0;
        }
        $transactionStarted = $this->multi();
        $hmset = [];
        if (isset($updatePlan['$set'])) {
            foreach ($updatePlan['$set'] as $field => $value) {
                if ($value === null) {
                    continue;
                }
                $hmset['$set.' . $field] = $value;
                unset($updatePlan['$unset'][$field]);
                foreach ($ids as $id) {
                    $this->redis->hdel($prefix . $id, '$unset.' . $field);
                }
            }
        }
        if (isset($updatePlan['$inc'])) {
            foreach ($updatePlan['$inc'] as $field => $value) {
                $this->redis->hincrby($prefix . $id, '$inc. ' . $field, $value);
            }
        }
        if (isset($updatePlan['$delete'])) {
            $hmset['$delete'] = '1';
        }
        if (isset($updatePlan['$unset'])) {
            foreach ($updatePlan['$unset'] as $field => $value) {
                $hmset['$unset. ' . $field] = $value;
                unset($hmset['$set. ' . $field]);
                foreach ($ids as $id) {
                    $this->redis->hdel($prefix . $id, '$set.' . $field);
                }
            }
        }
        foreach ($ids as $id) {
            $hmset['$where'] = $this->primaryKey . ' = ?';
            $hmset['$whereValues'] = json_encode([$id]);
            $this->redis->hmset($prefix . $id, $hmset);
        }
        foreach ($ids as $id) {
            $this->redis->sadd(static::BATCH_PREFIX . $this->maxDelay . ':' . $this->modelClass, $prefix . $id);
        }
        if ($transactionStarted) {
            $this->redis->exec();
            return count($ids);
        }
        return 0;
    }

    /**
     * Called instead of regular update() when the storage is in the purge mode.
     * Cancels delayed changes ($delete, $set, $unset) on the updated fields.
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  array $updatePlan Update plan — https://docs.mongodb.org/manual/reference/operator/update/#id1
     * @param  callable $cb = null Callback
     *
     * @return boolean Success
     * @throws \Exception
     */
    protected function updatePurge(GenericCond $where, $updatePlan, $cb = null)
    {
        if ($cb !== null) {
            $cb();
        }
        $prefix = static::HASH_PREFIX . $this->prefix;
        $ids = $where->getIds();
        if (!count($updatePlan)) {
            return true;
        }
        $transactionStarted = $this->multi();
        if (isset($updatePlan['$delete'])) {
            foreach ($ids as $id) {
                $this->redis->del($prefix . $id);
            }
        } else {
            if (isset($updatePlan['$set'])) {
                foreach ($updatePlan['$set'] as $field => $value) {
                    foreach ($ids as $id) {
                        $this->redis->hdel($prefix . $id, '$unset.' . $field);
                        $this->redis->hdel($prefix . $id, '$set.' . $field);
                    }
                }
            }
            if (isset($updatePlan['$unset'])) {
                foreach ($updatePlan['$unset'] as $field => $value) {
                    foreach ($ids as $id) {
                        $this->redis->hdel($prefix . $id, '$unset.' . $field);
                        $this->redis->hdel($prefix . $id, '$set.' . $field);
                    }
                }
            }
        }
        if ($transactionStarted) {
            return (bool)$this->redis->exec();
        }
        return true;
    }
}
