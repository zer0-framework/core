<?php

namespace Zer0\Model\Indexes;

/**
 * Class RedisHashByField
 * Redis-based index [keyStr => docId, ...]
 *
 * @package Zer0\Model\Indexes
 */
class RedisHashByField extends AbstractIndex
{
    use \Zer0\Model\Traits\RedisBase;

    /** @var array Key */
    protected $key;

    /** @var array Value */
    protected $value;

    /**
     * Called when object is being created
     *
     * @param  array $obj Fields and values
     * @param boolean $upsertMode = false
     * @param callable $cb = null
     * @return integer
     */
    public function onCreate($obj, $upsertMode = false, $cb = null)
    {
        $method = $upsertMode ? 'hSet' : 'hSetNx';
        if ($cb !== null) {
            $key = $this->serializeConfFields('key', $obj);
            if ($key === '') {
                $cb(0);
                return;
            }
            $this->redisAsync->$method(
                $this->prefix,
                $key,
                $this->serializeConfFields('value', $obj),
                function ($redis) use ($cb) {
                    $cb($redis->result);
                }
            );
            return;
        }
        return $this->redis->$method(
            $this->prefix,
            $this->serializeConfFields('key', $obj),
            $this->serializeConfFields('value', $obj)
        );
    }

    /**
     * Called when object is deleted
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions
     * @param callable $cb = null
     * @return void
     */
    public function onDelete($where, $cb = null)
    {
        if ($cb !== null) {
            $this->redisAsync->multi(function ($redis) use ($where, $cb) {
                $modelClass = $this->modelClass;
                $primaryKey = $modelClass::primaryKeyScheme();
                $keyVarname = substr($this->key[0], 1);
                if (!isset($where->mongoNotation[$keyVarname])) {
                    $cb(0);
                    return;
                }
                $mongoNotation = $where->mongoNotation[$keyVarname];
                if (isset($mongoNotation['$in'])) {
                    $keys = $mongoNotation['$id'];
                } elseif (is_scalar($mongoNotation)) {
                    $keys = [$mongoNotation];
                } else {
                    $cb(0);
                    return;
                }
                foreach ($keys as $key) {
                    $redis->hdel(
                        $this->prefix,
                        $key
                    );
                }
                $this->exec(function ($redis) use ($cb) {
                    $cb($redis->result);
                });
            });
            return;
        }
        $transactionStarted = $this->multi();
        $modelClass = $this->modelClass;
        $primaryKey = $modelClass::primaryKeyScheme();
        $keyVarname = substr($this->key[0], 1);
        if (!isset($where->mongoNotation[$keyVarname])) {
            return 0;
        }
        $mongoNotation = $where->mongoNotation[$keyVarname];
        if (isset($mongoNotation['$in'])) {
            $keys = $mongoNotation['$id'];
        } elseif (is_scalar($mongoNotation)) {
            $keys = [$mongoNotation];
        } else {
            $cb(0);
            return;
        }
        foreach ($keys as $key) {
            $this->redis->hdel(
                $this->prefix,
                $key
            );
        }
        if ($transactionStarted) {
            return array_sum($this->exec());
        }
    }

    /**
     * Called when object is updated
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the update
     * @param  array $updatePlan Update plan in mongo notation (https://docs.mongodb.org/manual/reference/operator/update/#id1)
     * @param callable $cb = null
     * @return void
     */
    public function onUpdate($where, $updatePlan, $cb = null)
    {
        if ($cb !== null) {
            $cb(true);
            return;
        }
    }

    /**
     * Retrieve data from index.
     *
     * @param \Zer0\Model\Expressions\Conditions\Generic $where WHERE clause
     * @param array $fields Fields to fetch.
     * @param \Zer0\Model\Expressions\OrderBy &$orderBy ORDER BY clause
     * @param integer $offset = 0 Offset
     * @param integer $limit = null
     * @param array $innerJoins = []
     * @param \Zer0\Model\Expressions\GroupByClause $groupBy = null GROUP BY clause
     * @param callable $cb = null
     * @return null|array Array of items in storage (array of arrays).
     *                    Null signifies that index is not applicable.
     */
    public function fetch(
        $where,
        $fields = [],
        &$orderBy = null,
        $offset = 0,
        $limit = null,
        $innerJoins = [],
        $groupBy = null,
        $cb = null
    ) {
        if ($groupBy !== null || $innerJoins) {
            // @TODO: implement a hybrid mode where these operations use IDs from the index
            if ($cb !== null) {
                $cb(null);
            }
            return null;
        }

        $mongoNotation = $where->mongoNotation;
        // Some conditions remain which need to be checked against index contents

        $values = [];
        $used = [];
        if (count($mongoNotation) === 1) {
            // We have only one field
            $prop = key($mongoNotation);
            $cond = $mongoNotation[$prop];
            if (!is_array($cond)) {
                $cond = ['$in' => [$cond]];
            }
            if (is_array($cond) && count($cond) === 1 && isset($cond['$in'])) {
                // and the field has only $in condition applied
                foreach ($cond['$in'] as $value) {
                    $key = $this->serializeConfFields('key', [$prop => $value], true, $used);
                    if ($value === false || !$used) {
                        if ($cb !== null) {
                            $cb(null);
                        }
                        return null;
                    }
                    $values[] = $value;
                }
                if ($cb !== null) {
                    $this->redisAsync->hmget($this->prefix, ... $values + [
                            PHP_INT_MAX => function ($redis) use ($cb, &$values) {
                                $objects = [];
                                foreach ($redis->result as $k => $value) {
                                    if ($value === null) {
                                        continue;
                                    }
                                    $key = $values[$k];
                                    $obj = [];
                                    $this->deserializeConfFields('key', $key, $obj);
                                    $this->deserializeConfFields('value', $value, $obj);
                                    $objects[] = $obj;
                                }
                                $cb($objects);
                            }
                        ]);
                    return;
                }
                $objects = [];
                foreach ($this->redis->hmget($this->prefix, $values) as $key => $value) {
                    $obj = [];
                    $this->deserializeConfFields('key', $key, $obj);
                    $this->deserializeConfFields('value', $value, $obj);
                    $objects[] = $obj;
                }
                return $objects;
            }
            if ($cb !== null) {
                $cb(null);
            }
            return null;
        }
    }
}
