<?php

namespace Zer0\Model\Storages;

use Zer0\Model\Expressions\Conditions\Generic as GenericCond;
use Zer0\Model\Expressions\GroupByClause;
use Zer0\Model\Expressions\OrderByClause;
use Zer0\Model\Subtypes\Field;
use Zer0\Model\Traits\ConfigTrait;

/**
 * Class Generic
 * @package Zer0\Model\Storages
 */
abstract class Generic
{
    use ConfigTrait;

    /**
     * @var bool
     */
    protected $queryCheck = true;

    /**
     * __call
     * @param  string $method Method name
     * @param  array $args Arguments
     * @return mixed
     */
    public function __call($method, $args)
    {
        return null;
    }

    /**
     * __get
     * @param $prop
     * @return mixed
     */
    public function __get($prop)
    {
        if (isset($this->{$prop})) {
            return $this->{$prop};
        }
        return null;
    }

    /**
     * Retrieve data from storage. Some classes may not implement this method, for example DelayedRedis, which
     * is used only to create/update/delete data in storage, but not to read.
     * (Stub)
     *
     * @param GenericCond $where WHERE clause
     * @param array $fields Fields to fetch.
     * @param OrderByClause $orderBy ORDER BY clause
     * @param integer $offset = 0 Offset
     * @param integer $limit = null
     * @param array $innerJoins = []
     * @param  GroupByClause $groupBy = null GROUP BY clause
     * @param callable $cb = null
     * @return void Array of items in storage (array of arrays)
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
        if ($cb !== null) {
            $cb(null);
        }
        return;
    }

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
        return;
    }

    /**
     * Update object in storage.
     * (Stub)
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  array $updatePlan
     * @return void
     */
    public function update(GenericCond $where, $updatePlan)
    {
        return;
    }

    /**
     * Deletes objects
     *
     * (Stub)
     * @param \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @return void Number of deleted objects
     */
    public function delete(GenericCond $where)
    {
        return;
    }

    /**
     * Return IDs (primary key values) of items matching $where.
     * (Stub)
     *
     * @param \Zer0\Model\Expressions\Conditions\Generic $where
     * @return void [id1, id2, ...]
     */
    public function getIds(GenericCond $where)
    {
        return;
    }

    /**
     * Retrieve number of matched items
     * (Stub)
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  GroupByClause $groupBy = null GROUP BY clause
     * @param callable $cb = null
     * @return void
     */
    public function count(GenericCond $where, $limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        if ($cb !== null) {
            $cb(null);
        }
        return;
    }

    /**
     * Prepares $innerJoins
     * @param  array &$innerJoins
     * @param  array $allowedIndexes Array of classes
     * @return boolean
     */
    protected function prepareInnerJoins(&$innerJoins, $allowedIndexes = [])
    {
        foreach ($innerJoins as &$join) {
            list($index, $on, $fields) = $join;
            if ($allowedIndexes) {
                $found = false;
                foreach ($allowedIndexes as $class) {
                    if ($index instanceof $class) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    // Query cannot be performed with this storage
                    return false;
                }
            }
            $mongoNotationOn = $on->mongoNotation;
            foreach ($mongoNotationOn as $left => $right) { // @TODO: nested conditions
                $leftField = (new Field($left))->parseName();
                if ($right instanceof Field) { // Field - Field relation
                    $rightField = $right->parseName();

                    if (isset($leftField['model']) && $this->modelName === $leftField['model']
                    ) { // If 'base.field = joined.field'
                        unset($mongoNotationOn[$left]);
                        $mongoNotationOn[(string) $right] = new Field($left);
                    }
                    if ($this->queryCheck) {
                        if ($this->value !== ['$' . $rightField['name']]) {
                            // Query cannot be performed with this storage
                            return false;
                        }
                    }
                }
            }
            $join[1] = $on->mutateWithMongoNotation($mongoNotationOn);
        }
        unset($join);
        return true;
    }
}
