<?php

namespace Zer0\Model\Storages;

use Core\App;
use RedisClient\Pipeline\PipelineInterface;
use Zer0\Model\Expressions\Conditions\Generic as GenericCond;
use Zer0\Model\Storages\Interfaces\ReadableInterface;

/**
 * Class RedisHash
 * @package Zer0\Model\Storages
 */
class RedisHash extends Generic implements ReadableInterface
{
    use \Zer0\Model\Traits\RedisBase;

    /**
     * Primary key
     * @var string
     */
    protected $primaryKey;

    /**
     *
     * @param GenericCond $where WHERE clause
     * @param  array $fields Fields to fetch.
     * @param null $orderBy
     * @param  integer $offset = 0 Offset
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  GroupBy $groupBy = null GROUP BY clause
     * @param  callable $cb = null Callback
     * @return array|null Array of objects OR null
     * @throws \RedisClient\Exception\InvalidArgumentException
     * @callback $cb (array|null $objects Array ob objects OR null)
     */
    public function fetch(
        GenericCond $where,
        $fields = [],
        $orderBy = null,
        $offset = 0,
        $limit = null,
        $innerJoins = [],
        $groupBy = null,
        $cb = null
    ) {
        if (!$where instanceof \Zer0\Model\Expressions\Conditions\PrimaryKeyIn) {
            if ($cb !== null) {
                $cb(null);
            }
            return null;
        }
        $ids = $where->ids;
        foreach ($innerJoins as $join) {
            list($index, $on, $fields) = $join;
            $objects = $index->search($where, $ids);
            // @TODO: fields, etc
        }

        if ($cb !== null) {
            $this->redisAsync->multi(function ($redis) use ($cb, $ids, $fields) {
                if (count($fields)) {
                    foreach ($ids as $id) {
                        $redis->hmget($this->prefix . $id, $fields);
                    }
                } else {
                    foreach ($ids as $id) {
                        $redis->hgetall($this->prefix . $id);
                    }
                }
                $redis->exec(function ($redis) use ($cb) {
                    if ($redis->result === null) {
                        $cb(null);
                        return;
                    }
                    foreach ($redis->result as &$hash) {
                        $hash = $redis->arrayToHash($hash);
                        foreach ($hash as $field => $value) {
                            if (strncmp($field, '$null:', 6) === 0) {
                                unset($hash[$field]);
                                $field = substr($field, 6);
                                $hash[$field] = null;
                            }
                        }
                    }
                    $cb($redis->result);
                });
            });
            return;
        }
        $objects = $this->redis->pipeline(function (PipelineInterface $pipeline) use ($fields, $ids) {
            if (count($fields)) {
                foreach ($ids as $id) {
                    $this->redis->hmget($this->prefix . $id, $fields);
                }
            } else {
                foreach ($ids as $id) {
                    $this->redis->hgetall($this->prefix . $id);
                }
            }
        });


        if ($objects === null) {
            return null;
        }
        foreach ($objects as &$hash) {
            if (!$hash) {
                continue;
            }
            foreach ($hash as $field => $value) {
                if (strncmp($field, '$null:', 6) === 0) {
                    unset($hash[$field]);
                    $field = substr($field, 6);
                    $hash[$field] = null;
                }
            }
        }

        return $objects;
    }

    /**
     * Deletes objects
     * @param \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  callable $cb = null Callback
     * @return integer Number of deleted objects
     * @throws \Exception
     */
    public function delete(GenericCond $where, $cb = null)
    {
        $ids = $where->getIds();
        is_array($ids) || $ids = [$ids];
        $keys = [];
        foreach ($ids as $id) {
            $keys[] = $this->prefix . $id;
        }
        if ($cb !== null) {
            $this->redisAsync->del(...$keys + [
                    -1 => function ($redis) use ($cb) {
                        $cb($redis->result);
                    }
                ]);
            return;
        }
        return $this->redis->del($keys);
    }

    /**
     * Pushes new object into storage. Takes a $data array from which we generate a string containing
     * only the field values specified in the storages config array (the values of the 'value' key).
     * Score field(s) are also determined by the storages config array ('score' values), and
     * converted to string from $data.
     *
     * @param  array $data Field => value array with object data
     * @param  boolean $upsertMode = false
     * @param  callable $cb = null Callback, takes redis result as argument
     * @return null|array If array, redis->exec() results
     */
    public function create($data, $upsertMode = false, $cb = null)
    {
        if ($cb !== null) {
            $this->redisAsync->multi(function ($redis) use ($data, $upsertMode, $cb) {
                $key = $this->prefix . $data[$this->primaryKey];
                if ($this->watch) {
                    // Increments $key:ver integer for WATCH, let other processes know that data has changed
                    $redis->incr($key . $this->verSuffix);
                }
                $redis->del($key);
                foreach ($data as $field => $value) {
                    if ($value === null) {
                        unset($data[$field]);
                        $data['$null:' . $field] = '1';
                    }
                }
                if ($this->ttl !== null) {
                    $redis->expire($key, $this->ttl);
                    if ($this->watch) {
                        $redis->expire($key . $this->verSuffix, $this->ttl);
                    }
                }
                $redis->hmset($key, $data);
                $redis->exec(function ($redis) use ($cb) {
                    $cb($redis->result);
                });
            });
            return null;
        }
        $key = $this->prefix . $data[$this->primaryKey];
        $transactionStarted = $this->multi();

        if ($this->watch) {
            // Increments $key:ver integer for WATCH, let other processes know that data has changed
            $this->redis->incr($key . $this->verSuffix);
        }

        $this->redis->del($key);
        foreach ($data as $field => $value) {
            if ($value === null) {
                unset($data[$field]);
                $data['$null:' . $field] = '1';
            }
        }
        if ($this->ttl !== null) {
            $this->redis->expire($key, $this->ttl);
            if ($this->watch) {
                $this->redis->expire($key . $this->verSuffix, $this->ttl);
            }
        }
        $this->redis->hmset($key, $data);
        if ($transactionStarted) {
            return $this->exec();
        }
    }

    /**
     * Execute 'transaction' in Redis. Performs set, increment and unset operations as specified in
     * $updatePlan across Redis hash objects matching $where condition.
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  array $updatePlan Update plan — https://docs.mongodb.org/manual/reference/operator/update/#id1
     * @param  callable $cb = null Callback
     * @return boolean Success
     * @throws \Exception
     */
    public function update(GenericCond $where, $updatePlan, $cb = null)
    {
        if ($cb !== null) {
            $this->redisAsync->multi(function ($redis) use ($cb, $where, $updatePlan) {
                $ids = $where->getIds();
                if (!count($updatePlan)) {
                    return true;
                }
                foreach ($ids as $id) {
                    if ($this->watch) {
                        $redis->incr($this->prefix . $id . $this->verSuffix);
                    }
                    if ($this->ttl !== null) {
                        $redis->expire($this->prefix . $id, $this->ttl);
                        if ($this->watch) {
                            $this->redis->expire($this->prefix . $id . $this->verSuffix, $this->ttl);
                        }
                    }
                }
                if (isset($updatePlan['$set'])) {
                    foreach ($updatePlan['$set'] as $field => $value) {
                        if ($value === null) {
                            unset($updatePlan['$set'][$field]);
                            $updatePlan['$unset'][$field] = '1';
                        }
                    }
                }
                if (isset($updatePlan['$unset'])) {
                    if (!isset($updatePlan['$set'])) {
                        $updatePlan['$set'] = [];
                    }
                    foreach ($updatePlan['$unset'] as $field => $value) {
                        foreach ($ids as $id) {
                            $redis->hdel($this->prefix . $id, $field);
                        }
                        $updatePlan['$set']['$null:' . $field] = '1';
                    }
                }
                if (isset($updatePlan['$set'])) {
                    foreach ($ids as $id) {
                        foreach ($updatePlan['$set'] as $setItem) {
                            if (strpos($setItem, '$null:') !== 0) {
                                $redis->hdel($this->prefix . $id, '$null:' . $setItem);
                            }
                        }
                        $redis->hmset($this->prefix . $id, $updatePlan['$set']);
                    }
                }
                if (isset($updatePlan['$inc'])) {
                    foreach ($updatePlan['$inc'] as $field => $value) {
                        $op = is_float($value) ? 'hIncrByFloat' : 'hIncrBy';
                        foreach ($ids as $id) {
                            $redis->$op($this->prefix . $id, $field, $value);
                        }
                    }
                }
                $redis->exec(function ($redis) use ($cb) {
                    $cb((bool)$redis->result);
                });
            });
            return;
        }
        $ids = $where->getIds();
        if (!count($updatePlan)) {
            return true;
        }
        $this->redis->multi();
        foreach ($ids as $id) {
            if ($this->watch) {
                $this->redis->incr($this->prefix . $id . $this->verSuffix);
            }
            if ($this->ttl !== null) {
                $this->redis->expire($this->prefix . $id, $this->ttl);
                if ($this->watch) {
                    $this->redis->expire($this->prefix . $id . $this->verSuffix, $this->ttl);
                }
            }
        }
        if (isset($updatePlan['$set'])) {
            foreach ($updatePlan['$set'] as $field => $value) {
                if ($value === null) {
                    unset($updatePlan['$set'][$field]);
                    $updatePlan['$unset'][$field] = '1';
                }
            }
        }
        if (isset($updatePlan['$unset'])) {
            if (!isset($updatePlan['$set'])) {
                $updatePlan['$set'] = [];
            }
            foreach ($updatePlan['$unset'] as $field => $value) {
                foreach ($ids as $id) {
                    $this->redis->hdel($this->prefix . $id, $field);
                }
                $updatePlan['$set']['$null:' . $field] = '1';
            }
        }
        if (isset($updatePlan['$set'])) {
            foreach ($ids as $id) {
                foreach ($updatePlan['$set'] as $key => $setItem) {
                    if (strpos($setItem, '$null:') !== 0) {
                        $this->redis->hdel($this->prefix . $id, '$null:' . $key);
                    }
                }
                $this->redis->hmset($this->prefix . $id, $updatePlan['$set']);
            }
        }
        if (isset($updatePlan['$inc'])) {
            foreach ($updatePlan['$inc'] as $field => $value) {
                $op = is_float($value) ? 'hIncrByFloat' : 'hIncrBy';
                foreach ($ids as $id) {
                    $this->redis->$op($this->prefix . $id, $field, $value);
                }
            }
        }
        return (bool)$this->redis->exec();
    }

    /**
     * Returns the number of matched objects
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  \Zer0\Model\Expressions\GroupByClause $groupBy = null GROUP BY clause
     * @param  callable $cb = null
     * @return integer|null
     */
    public function count(GenericCond $where, $limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        if ($cb !== null) {
            if ($innerJoins) {   // @TODO: $innerJoins
                $cb(null);
                return null;
            }
            if (!isset($this->primaryKey) || !$where instanceof \Zer0\Model\Expressions\Conditions\In) {
                $cb(null);
                return null;
            }
            if ($where->field !== $this->primaryKey) {
                $cb(null);
                return null;
            }
            $this->redisAsync->multi(function ($redis) use ($cb, $where, $innerJoins, $limit) {
                foreach ($where->in as $id) {
                    $redis->exists($this->prefix . $id);
                }
                $redis->exec(function ($redis) use ($cb, $limit) {
                    $count = array_sum($redis->result);
                    $count = $limit !== null ? min($count, $limit) : $count;
                    $cb($count);
                });
            });
            return;
        }
        if ($innerJoins) {   // @TODO: $innerJoins
            return null;
        }
        if (!isset($this->primaryKey) || !$where instanceof \Zer0\Model\Expressions\Conditions\In) {
            return null;
        }
        if ($where->field !== $this->primaryKey) {
            return null;
        }
        $this->redis->multi();
        foreach ($where->in as $id) {
            $this->redis->exists($this->prefix . $id);
        }
        $count = array_sum($this->redis->exec());
        return $limit !== null ? min($count, $limit) : $count;
    }
}
