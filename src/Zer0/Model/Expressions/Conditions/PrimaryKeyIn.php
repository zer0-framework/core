<?php

namespace Zer0\Model\Expressions\Conditions;

use PHPDaemon\Core\ComplexJob;
use Zer0\Model\Expressions\GroupByClause;
use Zer0\Model\Expressions\OrderByClause;
use Zer0\Model\Result\ResultMap;
use Zer0\Model\Storages\Interfaces\ReadableInterface;

/**
 * 'primary_key IN(?)'
 */
class PrimaryKeyIn extends In
{
    /**
     * Count number of objects, calling each storage's count() method until data is retrieved.
     *
     * @param  integer $limit = null [description]
     * @param  array $innerJoins = []
     * @param null $groupBy
     * @param  callable $cb = null Callback
     * @return integer|null
     * @throws \Exception
     */
    public function count($limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        return count($this->fetch([$this->field], null, 0, $limit, $innerJoins)->objects);
    }

    /**
     * Fetch objects from storage, calling each storage's fetch() method until data is retrieved.
     *
     * @param  array $fields = [] Fields to fetch
     * @param  OrderByClause $orderBy = null
     * @param  integer $offset = 0 Offset
     * @param  integer $limit = null Limit
     * @param  array $innerJoins = []
     * @param  GroupByClause $groupBy = null
     * @param  callable $cb = null Callback
     * @return void|ResultMap
     * @throws \Exception
     */
    public function fetch(
        $fields = [],
        $orderBy = null,
        $offset = 0,
        $limit = null,
        $innerJoins = [],
        $groupBy = null,
        $cb = null
    ) {
        if ($cb !== null) {
            return $this->fetchAsync($fields, $orderBy, $offset, $limit, $innerJoins, $groupBy, $cb);
        }
        // Check that we have 'in' expressions set
        if ($this->in === null || !count($this->in)) {
            return new ResultMap([]);
        }

        $arr = [];
        $inOrig = $this->in;
        $ids =& $this->in;

        $class = get_class($this->model);
        $checkFields = array_flip($fields ? $fields : array_keys($class::rules())); // @TODO: optimize
        /** @var \Zer0\Model\Storages\Generic[] $prev */
        $prev = [];
        foreach ($this->model->storages() as $storage) {
            if (!$storage instanceof ReadableInterface) {
                continue;
            }
            $objects = $storage->fetch(
                $this,
                $prev ? [] : $fields,
                $orderBy,
                $offset,
                $limit,
                $innerJoins,
                $groupBy
            );
            if ($objects) {
                $ids = array_values($ids);
                foreach ($objects as $i => $obj) {
                    if (!is_array($obj) || !$obj) {
                        continue;
                    }
                    /*foreach ($obj as $field => $value) {
                        if ($value === false) {
                            unset($obj[$field]);
                        }
                    }*/
                    if ($diff = array_diff_key($checkFields, $obj)) {
                        continue;
                    }
                    foreach ($prev as $ps) {
                        $ps->create($obj);
                    }
                    $arr[$ids[$i]] = $obj;
                    unset($ids[$i]);
                }
            }
            if (!count($ids)) {
                break;
            }

            // Commented out due to performance issues
            $storage->watch($ids);
            $storage->multi();
            $prev[] = $storage;
        }
        foreach ($prev as $ps) {
            $ps->exec();
        }
        $this->in = $inOrig;
        return new ResultMap($arr);
    }

    /**
     * Fetch objects from storage, calling each storage's fetch() method until data is retrieved.
     *
     * @param  array $fields = [] Fields to fetch
     * @param  OrderByClause $orderBy = null
     * @param  integer $offset = 0 Offset
     * @param  integer $limit = null Limit
     * @param  array $innerJoins = []
     * @param  GroupByClause $groupBy = null
     * @param  callable $cb Callback
     * @return void
     * @throws \Exception
     */
    public function fetchAsync(
        $fields = [],
        $orderBy = null,
        $offset = 0,
        $limit = null,
        $innerJoins = [],
        $groupBy = null,
        $cb
    ) {
        // Check that we have 'in' expressions set

        if ($this->in === null || !count($this->in)) {
            $cb(new ResultMap([]));
            return;
        }


        $arr = [];
        $inOrig = $this->in;
        $ids =& $this->in;

        $class = get_class($this->model);
        //$checkFields = array_flip($fields ? $fields : array_keys($class::rules())); // @TODO: optimize
        $checkFields = [];
        /** @var \Zer0\Model\Storages\Generic[] $prev */
        $storages = $this->model->storages();
        $prev = [];
        $cj = new ComplexJob(function () use (&$prev, $inOrig, $cb, &$arr) {
            $this->in = $inOrig;
            $cb(new ResultMap($arr));
        });
        $cj->maxConcurrency(1)->more(
            function ($cj) use (
                $storages,
                $fields,
                $orderBy,
                $offset,
                $limit,
                $innerJoins,
                $groupBy,
                &$arr,
                $cb,
                $inOrig,
                &$ids,
                &$prev,
                $checkFields
            ) {
                foreach ($storages as $k => $storage) {
                    if (!count($ids)) {
                        return;
                    }
                    yield $k => function ($jobname, $cj) use (
                        $fields,
                        $orderBy,
                        $offset,
                        $limit,
                        $innerJoins,
                        $groupBy,
                        &$arr,
                        $cb,
                        $inOrig,
                        &$ids,
                        $storage,
                        &$prev,
                        $checkFields
                    ) {
                        if (!count($ids)) {
                            $cj[$jobname] = null;
                            return;
                        }
                        $storage->fetch(
                            $this,
                            $fields,
                            $orderBy,
                            $offset,
                            $limit,
                            $innerJoins,
                            $groupBy,
                            function ($objects) use (
                                $cj,
                                $jobname,
                                &$arr,
                                $cb,
                                $inOrig,
                                &$ids,
                                $storage,
                                &$prev,
                                $checkFields
                            ) {
                                if ($objects) {
                                    $ids = array_values($ids);
                                    foreach ($objects as $i => $obj) {
                                        if (!is_array($obj) || !$obj) {
                                            continue;
                                        }
                                        foreach ($obj as $field => $value) {
                                            if ($value === false) {
                                                unset($obj[$field]);
                                            }
                                        }
                                        if (array_diff_key($checkFields, $obj)) {
                                            continue;
                                        }
                                        foreach ($prev as $ps) {
                                            $ps->create($obj, false, function () {
                                            });
                                        }
                                        $arr[$ids[$i]] = $obj;
                                        unset($ids[$i]);
                                    }
                                }
                                if (count($ids)) {
                                    $prev[] = $storage;
                                }
                                $cj[$jobname] = true;
                            }
                        );
                    };
                }
            }
        );
        $cj();
    }

    /**
     * Returns objects' IDs
     * @return array
     */
    public function getIds()
    {
        return $this->in;
    }
}
