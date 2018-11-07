<?php

namespace Zer0\Model\Storages;

use Zer0\App;
use Zer0\Drivers\PDO\PDO;
use Zer0\Model\Exceptions\UnsupportedActionException;
use Zer0\Model\Expressions\Conditions\Generic as GenericCond;
use Zer0\Model\Expressions\GroupByClause;
use Zer0\Model\Expressions\OrderByClause;
use Zer0\Model\Storages\Interfaces\ReadableInterface;
use Zer0\Model\Subtypes\Field;

/**
 * Class PGSQL
 * @package Zer0\Model\Storages
 */
class PGSQL extends Generic implements ReadableInterface
{

    /** @var PDO $sql PDO instance */
    protected $sql;

    /**
     * Asynchronous SQL client
     * @var \PHPDaemon\Clients\MySQL\Pool
     */
    protected $sqlAsync;

    /** @var string Primary key name */
    protected $primaryKey;

    /** @var string Table name */
    protected $table;

    /** @var string Connection name */
    protected $pdoName;

    /**
     * Upsert mode
     * @var bool
     */
    protected $updateOnDup = false;

    /**
     * Asynchronous SQL client
     * @var \PHPDaemon\Clients\MySQL\Pool
     * @var PDO
     */
    protected $sqlTransaction;

    /**
     * @var array
     */
    public $lastReturning = [];

    /**
     * @var bool
     */
    protected $queryCheck = false;

    /**
     * Constructor
     * @return void
     */
    public function init()
    {
        $this->sql = App::instance()->broker('PDO')->get($this->pdoName);
        if (defined('ZERO_ASYNC')) {
            $this->sqlAsync = App::instance()->broker('PDOAsync')->get($this->pdoName);
        }
    }

    /**
     *
     */
    public function lastReturning()
    {
        return $this->lastReturning;
    }

    /**
     * @return \Zer0\Drivers\PDO\PDO
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Return IDs (primary key values) of rows matching $where.
     *
     * @param \Zer0\Model\Expressions\Conditions\Generic $where
     * @return array [id, ...]
     * @throws \Exception
     */
    public function getIds(GenericCond $where)
    {
        $ids = [];
        $stmt = $this->sql->query('SELECT ' . $this->primaryKey
            . ' FROM ' . $this->table
            . (strlen($where->expr) ? ' WHERE ' . $where->expr : ''), $where->values);
        while ($row = $stmt->fetch()) {
            $ids[] = $row[$this->primaryKey];
        }
        return $ids;
    }

    /**
     * Retrieve data from table
     *
     * @param GenericCond $where WHERE clause
     * @param  array $fields Fields to fetch.
     * @param  OrderByClause $orderBy = null ORDER BY clause
     * @param  integer $offset = 0 Offset
     * @param  integer $limit = PHP_INT_MAX
     * @param  array $innerJoins = []
     * @param  GroupByClause $groupBy = null GROUP BY clause
     * @param  callable $cb = null Callback
     * @return array|null Array of objects OR null
     * @throws \Exception
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
        if ($limit === null) {
            $limit = PHP_INT_MAX;
        }
        if ($innerJoins) {
            if (!$this->prepareInnerJoins($innerJoins)) {
                return;
            }

            $mongoNotationWhere = [];
            foreach ($where->mongoNotation as $left => $right) {
                $leftParsed = (new Field($left))->parseName();
                if (count($leftParsed) === 1) {
                    $mongoNotationWhere[$this->table . '.' . $left] = $right;
                } else { // @TODO
                    $mongoNotationWhere[$left] = $right;
                }
            }
            $where->updateMongoNotation($mongoNotationWhere);
        }

        $joinClauses = '';
        foreach ($innerJoins as $item) {
            list($modelClass, $on, $joinFields) = $item;
            /**
             * @var PGSQL $storage
             */
            $storage = $modelClass::storage('PGSQL');

            $joinedTable = $storage->getTable();

            foreach ($joinFields as $field => $source) {
                if (!$fields) {
                    $fields = [$this->table . '.*'];
                }
                if (!$source instanceof Field) {
                    $source = new Field($source);
                }
                $parsed = $source->parseName();
                if ($parsed['model'] === $storage->modelName) {
                    $source = $storage->table . '.' . $parsed['name'];
                }
                $fields[] = $source . ' AS ' . $field;
            }

            //@TODO: nested conditions
            $mongoNotationOn = [];
            foreach ($on->getMongoNotation() as $left => $right) {
                $leftField = (new Field($left))->parseName();
                if ($leftField['model'] === $storage->modelName) {
                    $left = $joinedTable . '.' . $leftField['name'];
                }
                if ($right instanceof Field) {
                    $rightField = $right->parseName();
                    if ($rightField['model'] === $this->modelName) {
                        $right = new Field($this->table . '.' . $rightField['name']);
                    }
                }
                $mongoNotationOn[$left] = $right;
            }
            $on = $on->mutateWithMongoNotation($mongoNotationOn);
            $joinClauses .= ' INNER JOIN ' . $joinedTable . ' ON ' . $on;
        }

        $query = 'SELECT ' . (implode(', ', $fields) ?: '*') . ' FROM ' . $this->table
            . ($joinClauses !== '' ? ' ' . $joinClauses : '')
            . ($where->expr !== null ? ' WHERE ' . $where->expr : '')
            . ($groupBy ? ' GROUP BY ' . $groupBy->expr : '')
            . ($orderBy ? ' ORDER BY ' . $orderBy->expr : '')
            . ($offset !== 0 || $limit !== PHP_INT_MAX ?
                ' LIMIT ' . ((int)$limit) . ' OFFSET ' . ((int)$offset)
                : '');
        if ($cb !== null) {
            $plainSql = $this->sql->replacePlaceholders($query, $where->values);

            $this->sqlAsync->getConnection(function ($sql) use ($plainSql, $cb) {
                //    D(['fetch' => $plainSql, (string) new \Exception]);
                $sql->query($plainSql, function ($sql) use ($cb) {
                    $cb($sql->resultRows);
                });
            });
            return;
        }

        return $this->sql->query($query, $where->values)->fetchAll();
    }

    /**
     *
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Delete rows from table
     *
     * @param GenericCond $where
     * @param  callable $cb = null Callback
     * @return integer Number of deleted objects
     * @throws \Exception
     * @callback $cb (integer $affectedRows)
     */
    public function delete(GenericCond $where, $cb = null)
    {
        $query = 'DELETE FROM ' . $this->table . ' WHERE ' . $where->expr;
        if ($cb !== null) {
            $plainSql = $this->sql->replacePlaceholders($query, $where->values);
            $this->sqlAsync->getConnection(function ($sql) use ($plainSql, $cb) {
                $sql->query($plainSql, function ($sql) use ($cb) {
                    $cb($sql->affectedRows);
                });
            });
            return;
        }
        return $this->sql->query($query, $where->values)->rowCount();
    }

    /**
     * Pushes new object into storage. Takes a $data array from which we generate a string containing
     * only the field values specified in the storages config array (the values of the 'value' key).
     * Score field(s) are also determined by the storages config array ('score' values), and
     * converted to string from $data.
     *
     * @param  array $data Field => value array with object data
     * @param  boolean $upsertMode = false
     * @param  callable $cb = null Callback
     * @return void
     * @throws UnsupportedActionException
     * @callback $cb (integer $affectedRows)
     */
    public function create($data, $upsertMode = false, $cb = null)
    {
        $this->lastReturning = [];
        $query = 'INSERT INTO ' . $this->table
            . ' (' . join(', ', array_keys($data)) . ')';
        $values = [];
        $i = 0;
        $queryValues = '';
        foreach ($data as $field => $value) {
            $queryValues .= ($i++ > 0 ? ', ' : '') . '?';
            $values[] = $value;
        }
        $query .= ' VALUES (' . $queryValues . ')';
        if ($upsertMode && $this->updateOnDup) {
            if (count($data)) {
                foreach ($data as $field => $value) {
                    if ($field === $this->primaryKey) {
                        continue;
                    }
                    $query .= ' ON CONFLICT (' . $field . ' ) DO UPDATE SET ' . $field . ' = Excluded.' . $field;
                }
            }
        } else {
            if ($upsertMode && !$this->updateOnDup) {
                throw new UnsupportedActionException();
            } else {
                $query .= ' ON CONFLICT DO NOTHING';
            }
        }
        if ($this->primaryKey !== null) {
            $query .= ' RETURNING ' . $this->primaryKey;
        }
        if ($cb !== null) {
            $plainSql = $this->sql->replacePlaceholders($query, $values);
            $this->sqlAsync->getConnection(function ($sql) use ($plainSql, $cb) {
                //D(['create' => $plainSql]);
                $sql->query($plainSql, function ($sql) use ($cb) {
                    $cb($sql->affectedRows);
                });
            });
            return;
        }
        $stmt = $this->sql->query($query, $values);
        if ($this->primaryKey !== null) {
            $this->lastReturning = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Executes update plan in SQL. This 'transaction' is not a multi-statement transaction in the ACID sense,
     * but rather number a single UPDATE statement containing various operations on the fields in rows
     * matching $where.
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where Condition
     * @param  array $updatePlan Transaction plan — https://docs.mongodb.org/manual/reference/operator/update/#id1
     * @param  callable $cb = null Callback
     * @return boolean|null Success
     * @throws \Exception
     * @callback $cb (integer $affectedRows)
     */
    public function update(GenericCond $where, $updatePlan, $cb = null)
    {
        $setClause = '';
        $values = [];
        if (isset($updatePlan['$set'])) {
            foreach ($updatePlan['$set'] as $field => $value) {
                $setClause .= ($setClause !== '' ? ', ' : '') . $field . ' = ?';
                $values[] = $value;
            }
        }
        if (isset($updatePlan['$inc'])) {
            foreach ($updatePlan['$inc'] as $field => $value) {
                $value = (float)$value;
                $setClause .= ($setClause !== '' ? ', ' : '') . $field . ' =  ' . $field . ' + ?';
                $values[] = $value;
            }
        }
        if (isset($updatePlan['$unset'])) {
            foreach ($updatePlan['$unset'] as $field => $value) {
                $setClause .= ($setClause !== '' ? ', ' : '') . $field . ' = ?';
                $values[] = null;
            }
        }
        if ($setClause === '') {
            if ($cb !== null) {
                $cb(0);
            }
            return null;
        }
        $query = 'UPDATE ' . $this->table
            . ' SET ' . $this->sql->replacePlaceholders($setClause, $values)
            . ' WHERE ' . $this->sql->replacePlaceholders($where, $where->getValues());
        if ($cb !== null) {
            if ($this->sqlTransaction !== null) {
                $this->sqlTransaction->query($query, function ($sql) use ($cb) {
                    $cb($sql->affectedRows);
                });
                return null;
            }
            $this->sqlAsync->getConnection(function ($sql) use ($query, $cb) {
                $sql->query($query, function ($sql) use ($cb) {
                    $cb($sql->affectedRows);
                });
            });
            return null;
        }
        // @TODO: implement $this->sqlTransaction
        return $this->sql->query($query)->rowCount();
    }

    /**
     * Begin a transaction
     * @param  callable $cb = null Callback
     * @callback $cb ( $this )
     */
    public function begin($cb = null)
    {
        if ($cb !== null) {
            $this->sqlAsync->getConnection(function ($sql) use ($cb) {
                $sql->begin();
                $this->sqlTransaction = $sql;
                $cb($this);
            });
            return;
        }
        $this->sqlTransaction = $this->sql;
        $this->sqlTransaction->beginTransaction();
    }

    /**
     * Commit a transaction
     * @param  callable $cb = null Callback
     * @callback $cb (boolean $success)
     */
    public function commit($cb = null)
    {
        if ($cb !== null) {
            if ($this->sqlTransaction === null) {
                $cb(false);
                return;
            }
            $this->sqlTransaction->commit(function () use ($cb) {
                $cb(true);
            });
            $this->sqlTransaction = null;
            return;
        }
        $this->sqlTransaction->commit();
        $this->sqlTransaction = null;
    }


    /**
     * Rollback a transaction
     * @param  callable $cb = null Callback
     * @callback $cb (boolean $success)
     */
    public function rollback($cb = null)
    {
        if ($cb !== null) {
            if ($this->sqlTransaction === null) {
                $cb(false);
                return;
            }
            $this->sqlTransaction->rollback(function () use ($cb) {
                $cb(true);
            });
            $this->sqlTransaction = null;
            return;
        }
        $this->sqlTransaction->rollback();
        $this->sqlTransaction = null;
    }

    /**
     * Return the number of matched rows
     *
     * @param  \Zer0\Model\Expressions\Conditions\Generic $where
     * @param  integer $limit = null
     * @param  array $innerJoins = []
     * @param  OrderByClause $groupBy = null GROUP BY clause
     * @param  callable $cb = null Callback
     * @return integer|null
     * @throws \Exception
     * @callback $cb (integer $affectedRows)
     */
    public function count(GenericCond $where, $limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        // @TODO: $innerJoins
        if ($innerJoins) {
            return null;
        }

        $query = 'SELECT COUNT(*) n FROM ' . $this->table
            . (strlen($where->expr) ? ' WHERE ' . $where->expr : '')
            . ($groupBy ? ' GROUP BY ' . $groupBy->expr : '')
            . ($limit !== null ? ' LIMIT ' . ((int)$limit) : '');
        if ($cb !== null) {
            $plainSql = $this->sql->replacePlaceholders($query, $where->values);
            //D(['count' => $plainSql]);
            $this->sqlAsync->getConnection(function ($sql) use ($plainSql, $cb) {
                $sql->query($plainSql, function ($sql) use ($cb) {
                    $cb($sql->resultRows[0]['n'] ?? null);
                });
            });
            return;
        }

        return (int)$this->sql->query($query, $where->values)->fetchColumn();
    }
}
