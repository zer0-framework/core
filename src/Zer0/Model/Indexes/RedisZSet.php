<?php

namespace Zer0\Model\Indexes;

use Zer0\Model\Exceptions\MissingIndexDataException;

/**
 * Class RedisZSet
 * @package Zer0\Model\Indexes
 */
class RedisZSet extends AbstractIndex
{
    use \Zer0\Model\Traits\RedisBase;

    /** @var array Key */
    protected $key;

    /** @var array Score field to use for sorting */
    protected $score;

    /** @var string Score field to use for sorting */
    protected $scoreScheme;

    /** @var callback Purge ranges */
    protected $purgeRanges;

    /** @var array Value */
    protected $value;

    /** @var callable(RedisZSet $this, Conditions\Generic $where)   Callback called when ZSET is missing */
    protected $onMiss;

    /**
     * @var bool If true, purge() gets called automatically before each count() and fetch().
     * Not the best in terms of performance, but an easy way to go.
     */
    protected $autoPurge = true;

    /**
     * Called when object is being created
     *
     * @param  array $obj Fields and values
     * @param boolean $upsertMode = false
     * @param callable $cb = null
     * @return void
     */
    public function onCreate($obj, $upsertMode = false, $cb = null)
    {
        $score = $this->serializeConfFields('score', $obj, true);
        $value = $this->serializeConfFields('value', $obj, true);
        if ($value === false) {
            if ($cb) {
                $cb(false);
            }

            return;
        }

        if ($this->where) {
            $assert = $this->where->checkCondition($obj);
            if (!$assert) {
                if ($cb) {
                    $cb(false);
                }

                return;
            }
        }

        if ($cb !== null) {
            $this->redisAsync->zAdd(
                $this->prefix . $this->serializeConfFields('key', $obj),
                $score,
                $value,
                function ($redis) use ($cb) {
                    $cb($redis->result);
                }
            );
            return;
        }

        if ($value === false) {
            return;
        }
        $this->redis->zAdd(
            $this->prefix . $this->serializeConfFields('key', $obj),
            $score,
            $value
        );
    }

    /**
     * Called when object is deleted
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions
     * @param callable $cb = null
     * @return void
     * @throws \Exception
     */
    public function onDelete($where, $cb = null)
    {
        if ($cb !== null) {
            $this->redisAsync->multi(function ($redis) use ($where, $cb) {
                $modelClass = $this->modelClass;
                $primaryKey = $modelClass::primaryKeyScheme();
                foreach ($where->getIds() as $id) {
                    $obj[$primaryKey] = $id;
                    $value = $this->serializeConfFields('value', $obj, true);
                    if ($value === false) {
                        $redis->discard();
                        return;
                    }
                    $redis->zRem(
                        $this->prefix . $this->serializeConfFields('key', $obj),
                        $value
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
        foreach ($where->getIds() as $id) {
            $obj[$primaryKey] = $id;
            $value = $this->serializeConfFields('value', $obj, true);
            if ($value === false) {
                if ($transactionStarted) {
                    $this->discard();
                }
                return;
            }
            $this->redis->zrem(
                $this->prefix . $this->serializeConfFields('key', $obj),
                $value
            );
        }
        if ($transactionStarted) {
            $this->exec();
        }
    }

    /**
     * Called when object is updated
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the update
     * @param  array $updatePlan Update plan in mongo notation (https://docs.mongodb.org/manual/reference/operator/update/#id1)
     * @param callable $cb = null
     * @return void
     * @throws MissingIndexDataException
     */
    public function onUpdate($where, $updatePlan, $cb = null)
    {
        if ($cb !== null) {
            if (!isset($updatePlan['$set'])) {
                $cb(false);
                return;
            }
            $this->redisAsync->multi(function ($redis) use ($cb, $where, $updatePlan) {
                $obj = [];
                if (isset($updatePlan['$set'])) {
                    $obj = $updatePlan['$set'];
                }
                if (isset($updatePlan['$data'])) {
                    $obj = array_merge($obj, $updatePlan['$data']);
                }
                $modelClass = $this->modelClass;
                $primaryKey = $modelClass::primaryKeyScheme();
                foreach ($where->getIds() as $id) {
                    $obj[$primaryKey] = $id;
                    $score = $this->serializeConfFields('score', $obj, true);
                    $value = $this->serializeConfFields('value', $obj, true);

                    if ($score === false && $value === false) {
                        $cb(false);
                        $redis->discard();
                        return;
                    }
                    if ($score === false) {
                        throw new MissingIndexDataException('index ' . $this->name . ': \'score\' is missing some values');
                        $redis->discard();
                        $cb(false);
                        return;
                    }

                    if ($value === false) {
                        throw new MissingIndexDataException('index ' . $this->name . ': \'value\' is missing some values');
                        $redis->discard();
                        $cb(false);
                        return;
                    }
                    $redis->zAdd(
                        $this->prefix . $this->serializeConfFields('key', $obj),
                        $score,
                        $value
                    );
                }
                $redis->exec(function ($redis) use ($cb) {
                    $cb(true);
                });
            });
            return;
        }
        if (!isset($updatePlan['$set'])) {
            return;
        }
        $obj = [];
        if (isset($updatePlan['$set'])) {
            $obj = $updatePlan['$set'];
        }
        if (isset($updatePlan['$data'])) {
            $obj = array_merge($obj, $updatePlan['$data']);
        }
        $transactionStarted = null;
        $modelClass = $this->modelClass;
        $primaryKey = $modelClass::primaryKeyScheme();
        foreach ($where->getIds() as $id) {
            $obj[$primaryKey] = $id;
            $score = $this->serializeConfFields('score', $obj, true);
            $value = $this->serializeConfFields('value', $obj, true);
            if ($score === false && $value === false) {
                if ($transactionStarted) {
                    $this->discard();
                }
                return;
            }
            if ($score === false) {
                throw new MissingIndexDataException('index ' . $this->name . ': \'score\' is missing some values');
                if ($transactionStarted) {
                    $this->discard();
                }
                return;
            }
            if ($value === false) {
                throw new MissingIndexDataException('index ' . $this->name . ': \'value\' is missing some values');
                if ($transactionStarted) {
                    $this->discard();
                }
                return;
            }
            if ($transactionStarted === null) {
                $transactionStarted = $this->multi();
            }
            $this->redis->zAdd(
                $this->prefix . $this->serializeConfFields('key', $obj),
                $score,
                $value
            );
        }
        if ($transactionStarted) {
            $this->exec();
        }
    }

    /**
     * Returns the Redis key where the index chunk is stored
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the view
     * @param  array $conditions Join conditions
     * @return string Redis key
     */
    public function getRedisKeyForJoin($where, $conditions)
    {
        // @TODO Redis key depends on $conditions

        return $this->getRedisKey($where);
    }

    /**
     * Returns the Redis key where the index chunk is stored
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the view
     * @return string Redis key
     */
    public function getRedisKey($where)
    {
        $key = $this->prefix;
        if ($this->key !== null) {
            $key .= $this->serializeConfFields('key', $where->mongoNotation);
        }
        return $key;
    }

    /**
     * Retrieve data from index.
     *
     * @param \Zer0\Model\Expressions\Conditions\Generic $where WHERE clause
     * @param array $fields Fields to fetch.
     * @param \Zer0\Model\Expressions\OrderByClause &$orderBy ORDER BY clause
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
        if ($cb !== null) {
            $cb($this->fetch($where, $fields, $orderBy, $offset, $limit, $innerJoins, $groupBy));
            return;

            // @todo Check this moment
        }

        // @TODO: implement a hybrid mode where these operations use IDs from the index
        if ($groupBy || $innerJoins) {
            return null;
        }

        $modelClass = $this->modelClass;
        if ($modelClass::primaryKeyScheme(true) !== $this->value) {
            return;
        }

        $mongoNotation = $where->mongoNotation;
        if ($this->where) {
            foreach ($this->where->getMongoNotation() as $field => $value) {
                // Remove all fields that are part of the condition used to generate this index
                // (e.g. if index is generated on online=1 and where contains online=1, this condition will be
                // removed so that remaining where conditions only contain those fields that need to be checked
                // in this specific index's contents).
                if (isset($mongoNotation[$field]) && $mongoNotation[$field] === $value) {
                    unset($mongoNotation[$field]);
                } else {
                    return null;
                }
            }
        }

        if ($mongoNotation) {
            // Some conditions remain which need to be checked against index contents
            // @TODO: nested conditions
            $values = [];
            $used = [];
            if (count($mongoNotation) === 1) {
                // We have only one field
                $key = key($mongoNotation);
                $cond = $mongoNotation[$key];
                if (!is_array($cond)) {
                    $cond = ['$in' => [$cond]];
                }
                if (is_array($cond) && count($cond) === 1 && isset($cond['$in'])) {
                    // and the field has only $in condition applied
                    foreach ($cond['$in'] as $value) {
                        $value = $this->serializeConfFields('value', [$key => $value], true, $used);
                        if ($value === false || !$used) {
                            return null;
                        }
                        $values[] = $value;
                    }
                    $mongoNotation = [];
                }
            } else {
                $value = $this->serializeConfFields('value', $mongoNotation, true, $used);
                if ($value === false) {
                    return null;
                }
                $values[] = $value;
                $mongoNotation = array_diff_key($mongoNotation, $used);
            }
            if (!$mongoNotation) {
                if (!$values) {
                    return null;
                }
                $this->multi();
                if ($this->autoPurge) {
                    $this->purge($where);
                }
                $key = $this->getRedisKey($where);
                foreach ($values as $value) {
                    $this->redis->zscore($key, $value);
                }

                $i = 0;
                $raw = [];
                foreach (array_slice($this->exec(), -count($values)) as $score) {
                    if ($score !== false) {
                        $raw[$values[$i++]] = $score;
                    }
                }

                $orderDir = 0;

                if ($orderBy !== null) {
                    $scheme = [];
                    foreach ($orderBy->mongoNotation as $field => $dir) {
                        $scheme[] = '$' . $field;
                    }
                    if (($this->scoreScheme ?: $this->score) === $scheme) {
                        $orderBy = null;
                        $orderDir = $dir;
                        $orderByEntity = 'score';
                    }
                }
                if ($orderDir !== 0) {
                    if ($orderByEntity === 'score') {
                        if ($orderDir === 1) {
                            asort($raw, SORT_NATURAL);
                        } else {
                            arsort($raw, SORT_NATURAL);
                        }
                    }
                }

                $objects = [];

                foreach ($raw as $value => $score) {
                    $obj = [];
                    $this->deserializeConfFields('value', $value, $obj);
                    $this->deserializeConfFields('score', $score, $obj);
                    $objects[$value] = $obj;
                }
                return $objects;
            }
        }

        $this->multi();
        if ($this->autoPurge) {
            $this->purge($where);
        }
        $objects = [];
        $orderDir = 1;

        if ($orderBy !== null) {
            $scheme = [];
            foreach ($orderBy->mongoNotation as $field => $dir) {
                $scheme[] = '$' . $field;
            }
            if (($this->scoreScheme ?: $this->score) === $scheme) {
                $orderBy = null;
                $orderDir = $dir;
            }
        }

        if ($mongoNotation) {
            // Additional conditions
            $method = $orderDir === -1 ? 'zRevRangeByScore' : 'zRangeByScore';
            $min = '-inf';
            $max = '+inf';

            $scoreScheme = $this->scoreScheme ?: $this->score;
            foreach ($mongoNotation as $field => $cond) {
                if (!in_array('$' . $field, $scoreScheme)) {
                    return null;
                }
                if (!is_array($cond)) {
                    $min = $cond;
                    $max = $cond;
                } else {
                    foreach ($cond as $type => $val) {
                        if ($type === '$gt') {
                            $min = $val;
                        } elseif ($type === '$gte') {
                            $min = '(' . $val;
                        } elseif ($type === '$lt') {
                            $max = $val;
                        } elseif ($type === '$lte') {
                            $max = '(' . $val;
                        } elseif ($type === '$eq') {
                            $min = $val;
                            $max = $val;
                        }
                    }
                }
                unset($mongoNotation[$field]);
            }
            if ($mongoNotation) {
                return null;
            }
            $this->redis->$method(
                $this->getRedisKey($where),
                $min,
                $max,
                [
                    'limit' => [$offset, $limit === null ? -1 : $offset + $limit],
                    'withscores' => true,
                ]
            );
        } else {
            $method = $orderDir === -1 ? 'zRevRange' : 'zRange';
            $this->redis->$method(
                $this->getRedisKey($where),
                $offset,
                $limit === null ? -1 : $offset + $limit - 1,
                true
            );
        }
        $exec = $this->exec();
        if ($exec === null) {
            return null;
        }
        foreach (end($exec) as $value => $score) {
            $obj = [];
            $this->deserializeConfFields('value', $value, $obj);
            $this->deserializeConfFields('score', $score, $obj);
            $objects[$value] = $obj;
        }
        return $objects;
    }

    /**
     * Purges overdue items from the index
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the view
     * @param  array $options = ['returnPurged' => false, 'withscores' => false] An associative array of options.
     * @return void
     * @example User::index('online')->purge(User::any()->where(), ['returnPurged' => true])
     */
    public function purge($where, $options = [])
    {
        $key = $this->getRedisKey($where);
        $transactionStarted = $this->multi(true);
        $i = 0;
        $retNumbers = [];
        foreach ($this->callConfCallback('purgeRanges') ?: [] as $range) {
            // Remove elements within given score (sort value) range
            if (isset($range['score'])) {
                if (isset($options['returnPurged'])) {
                    $rangeOpts = [];
                    if (isset($options['withscores'])) {
                        $rangeOpts['withscores'] = (bool)$options['withscores'];
                    }
                    $retNumbers[] = $i++;
                    $this->redis->zrangebyscore(
                        $key,
                        $range['score']['min'],
                        $range['score']['max'],
                        $rangeOpts
                    );
                }
                ++$i;
                $this->redis->zremrangebyscore($key, $range['score']['min'], $range['score']['max']);
            }
            // Remove elements within given indexes, if specified
            if (isset($range['pos'])) {
                $retNumbers[] = $i++;
                $this->redis->zrange(
                    $key,
                    $range['pos']['min'],
                    $range['pos']['max'],
                    isset($options['withscores'])
                );
                ++$i;
                $this->redis->zremrangebyrank($key, $range['pos']['min'], $range['pos']['max']);
            }
        }
        if ($transactionStarted) {
            $exec = $this->exec();
            if (isset($options['returnPurged'])) {
                $ret = [];
                foreach ($retNumbers as $i) {
                    $ret = array_merge($ret, $exec[$i]);
                }
                return $ret;
            }
        }
    }

    /**
     * Retrieve number of matched items
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param \Zer0\Model\Expressions\GroupByClause $groupBy = null GROUP BY clause
     * @param  callable $cb = null
     * @return integer|null
     */
    public function count($where, $limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        if (!$this->where) {
            if ($cb) {
                $cb(null);
            }

            return null;
        }


        // @TODO: implement a hybrid mode where these operations use IDs from the index
        if ($innerJoins) {
            if ($cb) {
                $cb(null);
            }

            return null;
        }

        $mongoNotation = $where->mongoNotation; // @TODO: nested conditions
        foreach ($this->where->getMongoNotation() as $field => $value) {
            if (isset($mongoNotation[$field]) && $mongoNotation[$field] === $value) {
                unset($mongoNotation[$field]);
            } else {
                if ($cb) {
                    $cb(null);
                }

                return null;
            }
        }

        if (count($mongoNotation)) {
            if ($cb) {
                $cb(null);
            }

            return null;
        }

        if ($cb !== null) {
            $this->redisAsync->multi(function ($redis) use ($where, $limit, $cb) {
                if ($this->autoPurge) {
                    $this->purgeAsync($where, $redis);
                }
                $redis->zCount($this->getRedisKey($where), '-inf', '+inf'); // @TODO: implement boundaries
                $redis->exec(function ($redis) use ($cb, $limit) {
                    if (!$redis->result) {
                        $cb(null);
                        return;
                    }
                    $count = end($redis->result);
                    if ($limit !== null) {
                        $count = min($count, $limit);
                    }
                    $cb($count);
                });
            });
            return null;
        }
        if ($this->where === null) {
            return null;
        }
        if ($groupBy) {
            $modelClass = $this->modelClass;
            $primaryKey = $modelClass::primaryKeyScheme();
            $groupByFields = $groupBy->mongoNotation;
            if (!is_array($primaryKey)) {
                $primaryKey = [$primaryKey];
            }
            $pk = true;
            foreach ($primaryKey as $primaryKeyField) {
                if (!isset($groupByFields[$primaryKeyField])) {
                    $pk = false;
                    break;
                }
            }
            if (!$pk) {
                $scheme = [];
                foreach ($groupByFields as $field => $dir) {
                    $scheme[] = '$' . $field;
                }
                if (($this->value) !== $scheme) {
                    return null;
                }
            }
        }
        if ($innerJoins) {
            // @TODO: implement a hybrid mode where these operations use IDs from the index
            return null;
        }
        $mongoNotation = $where->mongoNotation; // @TODO: nested conditions
        foreach ($this->where->mongoNotation as $field => $value) {
            if (isset($mongoNotation[$field]) && $mongoNotation[$field] === $value) {
                unset($mongoNotation[$field]);
            } else {
                return null;
            }
        }
        if (count($mongoNotation)) {
            return null;
        }
        $this->redis->discard();
        $this->redis->multi();
        if ($this->autoPurge) {
            $this->purge($where);
        }
        $this->redis->zcount($this->getRedisKey($where), '-inf', '+inf'); // @TODO: implement boundaries
        $exec = $this->redis->exec();
        $count = end($exec);
        if ($limit !== null) {
            $count = min($count, $limit);
        }

        return $count;
    }

    /**
     * Purges overdue items from the index
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the view
     * @param \PHPDaemon\Clients\Redis\Connection $redis
     * @return void
     */
    public function purgeAsync($where, $redis)
    {
        $key = $this->getRedisKey($where);
        foreach ($this->callConfCallback('purgeRanges') ?: [] as $range) {
            // Remove elements within given score (sort value) range
            if (isset($range['score'])) {
                $redis->zRemRangeByScore($key, $range['score']['min'], $range['score']['max']);
            }
            // Remove elements within given indexes, if specified
            if (isset($range['pos'])) {
                $redis->zRemRangeByRank($key, $range['pos']['min'], $range['pos']['max']);
            }
        }
    }

    /**
     * Search
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Conditions of the view
     * @param  array &$onlyIds = null
     * @return array
     */
    public function search($where, &$onlyIds = null)
    {
        $key = $this->getRedisKey($where);
        $this->multi();
        $this->purge($where);
        if ($onlyIds === null) {
            unset($onlyIds);
            $onlyIds = $where->ids;
        }
        foreach ($onlyIds as $val) {
            $this->redis->zscore($key, $val);
        }
        $objects = [];
        $scores = $this->exec();
        $i = 0;
        foreach ($onlyIds as $k => &$value) {
            if (isset($scores[$i])) {
                $obj = [];
                $this->deserializeConfFields('value', $value, $obj);
                $this->deserializeConfFields('score', $scores[$i], $obj);
                $objects[$value] = $obj;
            } else {
                unset($onlyIds[$k]);
            }
            ++$i;
        }
        return $objects;
    }
}
