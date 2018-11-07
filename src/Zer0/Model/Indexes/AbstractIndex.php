<?php

namespace Zer0\Model\Indexes;

use Zer0\Model\Expressions\Conditions\Generic;
use Zer0\Model\Expressions\GroupByClause;
use Zer0\Model\Expressions\OrderByClause;
use Zer0\Model\Traits\ConfigTrait;

/**
 * Class AbstractIndex
 * @package Zer0\Model\Indexes
 */
abstract class AbstractIndex
{
    use ConfigTrait;

    /**
     * JOIN ... ON $defaultOn
     *
     * @var string
     */
    protected $defaultOn;

    /**
     * @var Generic|null
     */
    protected $where;


    /**
     * Index name
     * @var string
     */
    protected $name;

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
    }

    /**
     * Called when object is being updated
     *
     * @param  Generic $where Conditions of the update
     * @param  array $updatePlan Update plan in mongo notation (https://docs.mongodb.org/manual/reference/operator/update/#id1)
     * @param callable $cb = null
     * @return void
     */
    public function onUpdate($where, $updatePlan, $cb = null)
    {
    }

    /**
     * Called when object is being deleted
     *
     * @param  Generic $where Conditions of the update
     * @param callable $cb = null
     * @return void
     */
    public function onDelete($where, $cb = null)
    {
    }

    /**
     * Retrieve data from index
     * (Stub)
     *
     * @param string $where WHERE clause
     * @param array $fields Fields to fetch.
     * @param OrderByClause &$orderBy ORDER BY clause
     * @param integer $offset = 0 Offset
     * @param integer $limit = null
     * @param array $innerJoins = []
     * @param  GroupByClause $groupBy = null GROUP BY clause
     * @param callable $cb = null
     * @return void ?array Array of items in storage (array of arrays)
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
            $cb(null);
        }
        return;
    }

    /**
     * Retrieve number of matched items
     * (Stub)
     *
     * @param  Generic $where
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  \Zer0\Model\Expressions\GroupByClause $groupBy = null GROUP BY clause
     * @param  callable $cb = null
     * @return void ?int
     */
    public function count($where, $limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        if ($cb !== null) {
            $cb(null);
        }
        return;
    }

    /**
     * __call
     *
     * @param  string $method
     * @param  array $args
     * @return void
     */
    public function __call($method, $args)
    {
    }

    /**
     * __get
     *
     * @param  string $prop Property name
     * @return mixed
     */
    public function __get($prop)
    {
        return $this->{$prop};
    }

    /**
     * Init
     * @return void
     */
    protected function init()
    {
        if (is_string($this->where)) {
            $this->where = new Generic($this->where, null, null);
        }
    }
}
