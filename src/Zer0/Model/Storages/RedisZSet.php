<?php

namespace Zer0\Model\Storages;

use Zer0\Model\Expressions\Conditions\Generic as GenericCond;
use Zer0\Model\Storages\Interfaces\ReadableInterface;

/**
 * Class RedisZSet
 * @package Zer0\Model\Storages
 */
class RedisZSet extends Generic implements ReadableInterface
{
    use \Zer0\Model\Traits\RedisBase;

    /** @var string $key Redis key name */
    protected $key;

    /** * @var string $score Value to sort ZSet by */
    protected $score;

    /** @var string $value ZSet value for $key */
    protected $value;

    /**
     * Callback called when ZSET is missing
     * @var callable(RedisZSet $this, Conditions\Generic $where)
     */
    protected $onMiss;

    /**
     * Pushes new object into storage. Takes a $data array from which we generate a string containing
     * only the field values specified in the storages config array (the values of the 'value' key).
     * Score field(s) are also determined by the storages config array ('score' values), and
     * converted to string from $data.
     *
     * @param  array $data Field => value array with object data
     * @param  boolean $upsertMode = false
     * @return void
     */
    public function create($data, $upsertMode = false)
    {
        $key = $this->prefix . $this->serializeConfFields('key', $data);
        $score = $this->serializeConfFields('score', $data);
        $transactionStarted = $this->multi();
        if ($this->watch) {
            if (!isset($this->verIncr[$key])) {
                // Increment $key:ver integer for WATCH.
                $this->redis->incr($key . $this->verSuffix);
                $this->verIncr[$key] = true;
            }
        }
        $this->redis->zadd($key, $score, $this->serializeConfFields('value', $data));
        if ($transactionStarted) {
            $this->exec();
        }
    }

    /**
     * Deletes objects
     *
     * @param \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @return integer Number of deleted objects
     */
    public function delete(GenericCond $where)
    {
        $this->multi();
        $this->_delete($where->mongoNotation);
        return array_sum($this->exec());
    }

    /**
     * Delete items in zset matching given mongo-notated where clause
     *
     * @param array $mongoNotation Condition
     * @return void
     */
    protected function _delete($mongoNotation)
    {
        // @TODO: ... AND (... OR ...)
        if (isset($mongoNotation['$or'])) {
            foreach ($mongoNotation['$or'] as $or) {
                $this->_delete($or);
            }
            return;
        }
        $key = $this->prefix . $this->serializeConfFields('key', $mongoNotation);
        $this->redis->zrem($key, $this->serializeConfFields('value', $mongoNotation));
    }

    /**
     * Returns the number of matched objects
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  \Zer0\Model\Expressions\GroupByClause $groupBy = null GROUP BY clause
     * @param  callable $cb = null
     * @return integer
     */
    public function count(GenericCond $where, $limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        if ($cb !== null) {
            $this->fetch($where, [], null, 0, $limit, $innerJoins, $groupBy, function ($objects) use ($cb) {
                $cb(count($objects));
            });
        }
        $objects = $this->fetch($where, [], null, 0, $limit, $innerJoins, $groupBy);
        if ($objects === null) {
            return;
        }
        return count($objects);
    }

    /**
     * Fetch data from Redis matching
     * @param GenericCond $where WHERE clause
     * @param  array $fields Fields to fetch.
     * @param null $orderBy
     * @param  integer $offset = 0 Offset
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  GroupBy $groupBy = null GROUP BY clause
     * @param  callable $cb = null Callback
     * @return array|null Array of objects
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
        if ($limit === null) {
            $limit = -1;
        }

        if ($orderBy) {
            // Do some order notation translation:
            // "Ascending" = 1 (mongo) = zrange (redis)
            // "Descending" = -1 (mongo) = zrevrange (redis)
            foreach ($orderBy->mongoNotation as $field => $dir) {
                if (['$' . $field] === $this->score) {
                    $method = $dir === -1 ? 'zrevrange' : 'zrange';
                } else {
                    return;
                }
            }
        } else {
            $method = 'zrange';
        }

        if ($innerJoins && !$this->prepareInnerJoins($innerJoins, [
                \Zer0\Model\Indexes\RedisZSet::class // @TODO: add more types
            ])
        ) {
            return;
        }

        $mongoNotation = $where->mongoNotation;
        $keyStr = $this->serializeConfFields('key', $mongoNotation);
        $key = $this->prefix . $keyStr;
        // Fetch data from Redis
        for ($i = 0; $i < 5; ++$i) {
            $this->multi();

            $this->redis->exists($key);

            $tmpKey = false;
            foreach ($innerJoins as $join) {
                list($index, $on, $fields) = $join;
                $index->purge($where); // Commented due to a bug in Phpredis
                $zsets = [
                    $key,
                    $index->getRedisKeyForJoin($where, $on),
                ];
                if (!$tmpKey) {
                    $key = 'tmp:zset:' . uniqid();
                    $tmpKey = true;
                }
                $this->redis->zInter($key, $zsets);
            }

            $this->redis->$method($key, $offset, $limit, true);
            if ($tmpKey) {
                $this->redis->del($key);
            }

            $res = $this->redis->exec();
            if ($tmpKey) {
                array_pop($res);
            }
            $exists = array_shift($res);
            $set = array_pop($res);

            if (!$exists) {
                // If desired data is not present in Redis, invoke onMiss callback
                if (!isset($this->onMiss) || !call_user_func($this->onMiss, $this, $where)) {
                    return;
                }
            } else {
                $objects = [];
                foreach ($set as $value => $score) {
                    $object = [];
                    $this->deserializeConfFields('key', $keyStr, $object);
                    $this->deserializeConfFields('score', $score, $object);
                    $this->deserializeConfFields('value', $value, $object);
                    $objects[] = $object;
                }
                return $objects;
            }
        }
    }
}
