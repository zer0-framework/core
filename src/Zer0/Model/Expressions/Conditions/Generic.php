<?php

namespace Zer0\Model\Expressions\Conditions;

use InvalidArgumentException;
use PHPDaemon\Core\ComplexJob;
use Zer0\Model\Result\ResultList;
use Zer0\Model\Storages\Interfaces\ReadableInterface;

/**
 * @property array $mongoNotation
 */
class Generic extends \Zer0\Model\Expressions\Generic
{
    const TOKEN_OPERATOR = 1;
    const TOKEN_CONST_INT = 2;
    const TOKEN_FCALL = 3;
    const TOKEN_FIELD = 5;
    const TOKEN_PLACEHOLDER = 6;
    const TOKEN_SUBCLAUSE = 7;
    const TOKEN_EXTRA = 8;

    /** @var string $leftOperand Current left operand */
    protected $leftOperand;
    /** @var string $rightOperand Current right operand */
    protected $rightOperand;
    /** @var string $operator Current operator */
    protected $operator;
    /** @var integer $pos Current placeholder position */
    protected $pos = 0;
    /** @var integer $queryPos Current query position */
    protected $queryPos = 0;

    /**
     * @var array $stack Stack of nesting clauses:
     * @example [[&$this->level, $this->operator, $this->leftOperand], ...]
     */
    protected $stack = [];
    /** @var &array $level Current nesting level reference (mongo notation). */
    protected $level;
    /** @var array $mongoNotation Mongo notation */
    protected $mongoNotation;
    /** @var array $ids Array of document ids */
    protected $ids;

    /**
     * @var bool
     */
    protected $orNextClause = false;

    /**
     * __get
     * @param  string $prop Property name
     * @return mixed
     * @throws \Exception
     */
    public function __get($prop)
    {
        if ($prop === 'mongoNotation') {
            return $this->getMongoNotation();
        }
        if ($prop === 'ids') {
            return $this->getIds();
        }
        return $this->{$prop};
    }

    /**
     * @return array
     */
    public function getMongoNotation()
    {
        if ($this->mongoNotation !== null) {
            return $this->mongoNotation;
        }

        $this->pos = 0;
        $this->queryPos = 0;
        $this->level = [];

        $this->parseExpr($this->expr);
        return $this->mongoNotation = $this->level;
    }

    /**
     * Parses SQL expression into Mongo notation.
     * Mongo notation may be used for non-SQL storages, validation rules, etc.
     * This method is being called recursively to parse subclauses.
     * Values MUST be presented via placeholders except for integers which work both ways.
     *
     * @example 'foo = 1 OR bar = 2' will be turned into ['$or' => [['foo' => 1], ['bar' => 2]]]
     *          and stored in $this->level
     * @param  string $expr Expression to parse
     * @return void
     */
    protected function parseExpr($expr)
    {
        preg_replace_callback('~'
            . '(>=|<=|=|!=|>|<'                  // Comparison operator e.g. '='
            . '|(?<!\w)(?:AND|OR)(?!\w))'        // AND/OR operator
            . '|(\d+)'                           // Inline integer e.g. '123'
            . '|(NOT\s+IN|\w+)\s*\(((?:(?R)|.)*?)\)'      // Function call e.g. 'IN(?)'
            . '|(?<=\W|^)([\.\~\w]+)(?!\w)'      // Field name like e.g. 'id'
            . '|(\?|:\w+)'                       // Place holder e.g. ':foo' or '?'
            . '|\(((?:(?R)|.)*?)\)'              // Subclause like '(...)'
            . '|(.+?)'
            . '~i', function ($match) {
                static $tokens = [
                self::TOKEN_OPERATOR => 'OPERATOR',
                self::TOKEN_CONST_INT => 'CONST_INT',
                self::TOKEN_FCALL => 'FCALL',
                self::TOKEN_FIELD => 'FIELD',
                self::TOKEN_PLACEHOLDER => 'PLACEHOLDER',
                self::TOKEN_SUBCLAUSE => 'SUBCLAUSE',
                self::TOKEN_EXTRA => 'EXTRA',
            ];
                static $operators = [
                '>' => '$gt',
                '<' => '$lt',
                '>=' => '$gte',
                '<=' => '$lte',
                '!=' => '$ne',
                '=' => '$eq',
            ];
                $token = null;
                $value = '';
                foreach ($tokens as $tok => $name) {
                    if (isset($match[$tok]) && strlen($match[$tok])) {
                        $token = $tok;
                        $value = $match[$tok];
                        break;
                    }
                }
                if ($token === self::TOKEN_EXTRA) {
                    if (trim($value) !== '') {
                        // Unexpected token
                        throw new \Zer0\Model\Exceptions\IllegalSyntaxException(
                        'Illegal syntax ' . var_export($value, true)
                        . ' at pos. ' . $this->queryPos
                        . ' of ' . var_export($this->expr, true)
                    );
                    }
                } elseif ($token === self::TOKEN_FIELD) {
                    if ($this->operator === 'OR') {
                        if (!isset($this->level['$or'])) {
                            $this->level = ['$or' => [$this->level]];
                        }
                        $this->pushLevel();
                        $this->orNextClause = true;
                    }
                    if ($this->leftOperand === null) {
                        $this->leftOperand = $value;
                    } else {
                        $this->rightOperand = new \Zer0\Model\Subtypes\Field($value);

                        $this->rightOperand =& $dummy;
                        unset($dummy);

                        $this->operator = null;
                        $this->leftOperand = null;
                    }
                } elseif ($token === self::TOKEN_FCALL) {
                    $args = array_map('trim', explode(', ', $match[self::TOKEN_FCALL + 1]));
                    $fname = strtoupper($value);
                    if ($fname === 'IN' || preg_match('~^NOT\s+IN$~', $fname)) {
                        if ($this->leftOperand === null) {
                            throw new \Zer0\Model\Exceptions\IllegalSyntaxException(
                            'Illegal syntax ' . var_export($value . '(' . $args . ')', true)
                            . ' at pos. ' . $this->queryPos
                            . ' of ' . var_export($this->expr, true)
                        );
                        }
                        if ($fname === 'IN') {
                            $in =& $this->level[$this->leftOperand]['$in'];
                        } else {
                            $in =& $this->level[$this->leftOperand]['$nin'];
                        }
                        if ($in === null) {
                            $in = [];
                        }
                        foreach ($args as $arg) {
                            if (ctype_digit($arg)) {
                                $argVal = $arg;
                            } elseif ($arg === '?') {
                                $argVal = isset($this->values[$this->pos]) ? $this->values[$this->pos] : [];
                                ++$this->pos;
                            } else {
                                $arg = substr($arg, 1);
                                $argVal = isset($this->values[$arg]) ? $this->values[$arg] : null;
                            }
                            if (is_array($argVal)) {
                                $in = array_merge($in, $argVal);
                            } else {
                                $in[] = $argVal;
                            }
                        }
                        $in = array_unique($in);
                    } else {
                        throw new \Zer0\Model\Exceptions\UnsupportedActionException(
                        $fname . ' function is not supported by mongoNotation'
                        . ' at pos. ' . $this->queryPos
                        . ' of ' . var_export($this->expr, true)
                    );
                    }
                } elseif ($token === self::TOKEN_PLACEHOLDER) {
                    if ($value === '?') {
                        $this->rightOperand = isset($this->values[$this->pos]) ? $this->values[$this->pos] : null;
                        ++$this->pos;
                    } else {
                        $value = substr($value, 1);
                        $this->rightOperand = isset($this->values[$value]) ? $this->values[$value] : null;
                    }
                    if ($this->operator === '=') {
                        if (is_array($this->rightOperand)) {
                            if (count($this->rightOperand) > 1) {
                                $this->rightOperand = ['$in' => $this->rightOperand];
                            } elseif ($this->rightOperand) {
                                $this->rightOperand = $this->rightOperand[0];
                            }
                        }
                    }

                    $this->rightOperand =& $dummy;
                    unset($dummy);

                    $this->operator = null;
                    $this->leftOperand = null;
                } elseif ($token === self::TOKEN_CONST_INT) {
                    $this->rightOperand = (int)$value;

                    $this->rightOperand =& $dummy;
                    unset($dummy);

                    $this->operator = null;
                    $this->leftOperand = null;
                } elseif ($token === self::TOKEN_OPERATOR) {
                    $this->operator = strtoupper($value);
                    if ($this->operator === '&&') {
                        $this->operator = 'AND';
                    } elseif ($this->operator === '||') {
                        $this->operator = 'OR';
                    }

                    if ($this->operator === 'AND') {
                    } elseif ($this->operator === 'OR') {
                    } else {
                        if ($this->operator === '=') {
                            $this->level[$this->leftOperand] =& $this->rightOperand;
                        } else {
                            if (!isset($this->level[$this->leftOperand])) {
                                $this->level[$this->leftOperand] = [];
                            }
                            $this->level[$this->leftOperand][$operators[$this->operator]] =& $this->rightOperand;
                        }
                        if ($this->orNextClause) {
                            $sub = $this->level;
                            $this->popLevel();
                            $this->level['$or'][] = $sub;
                            $this->orNextClause = false;
                        }
                    }
                } elseif ($token === self::TOKEN_SUBCLAUSE) {
                    $operator = $this->operator;
                    $this->pushLevel();
                    $this->parseExpr($value);
                    $sub = $this->level;
                    $this->popLevel();

                    if ($operator === 'AND') {
                        if (!isset($this->level['$and'])) {
                            $this->level = ['$and' => [$this->level]];
                        }
                        $this->level['$and'][] = $sub;
                    } elseif ($operator === 'OR') {
                        if (!isset($this->level['$or'])) {
                            $this->level = ['$or' => [$this->level]];
                        }
                        $this->level['$or'][] = $sub;
                    } else {
                        $this->level = array_merge($this->level ?: [], $sub);
                    }
                }
                if ($token !== self::TOKEN_SUBCLAUSE) {
                    $this->queryPos += strlen($match[0]);
                }
            }, strtr($expr, ['`' => '']));
    }

    /**
     * Pushes a new nesting level to the stack
     * @return void
     */
    protected function pushLevel()
    {
        $this->stack[] = [&$this->level, $this->operator, $this->leftOperand];
        $newLevel = [];
        $this->level =& $newLevel;
        $this->operator = null;
        $this->leftOperand = null;
    }

    /**
     * Pops a nesting level from the stack
     * @return void
     */
    protected function popLevel()
    {
        $last = count($this->stack) - 1;
        $this->level =& $this->stack[$last][0];
        $this->operator = $this->stack[$last][1];
        $this->leftOperand = $this->stack[$last][2];
        array_pop($this->stack);
    }

    /** @noinspection PhpInconsistentReturnPointsInspection */
    /**
     * @return array|null|string[]
     * @throws \Exception
     */
    /**
     * @return array|null|string[]
     * @throws \Exception
     */
    /**
     * @return ?string[]
     * @throws \Exception
     */
    public function getIds()
    {
        if ($this->ids !== null) {
            return $this->ids;
        }
        foreach ($this->model->storages() as $storage) {
            if (($ids = $storage->getIds($this)) !== null) {
                return $this->ids = $ids;
            }
        }
        return null;
    }

    /**
     * Mutate with mongoNotation
     * @param $mongoNotation
     * @return Generic
     */
    public function mutateWithMongoNotation($mongoNotation)
    {
        $obj = new static(null, $this->values, $this->model);
        $obj->updateMongoNotation($mongoNotation);
        return $obj;
    }

    /**
     * Updates $mongoNotation
     * @param $mongoNotation
     * @return void
     */
    public function updateMongoNotation($mongoNotation)
    {
        $this->values = [];
        $this->expr = $this->compileMongoToSql($mongoNotation);
        $this->mongoNotation = $mongoNotation;
    }

    /**
     * Compiles mongo notation into SQL
     * Used in LEFT JOIN implementation
     * Functionality is currently limited,
     * supported $ operators: $or, $and, $eq
     * @param  array $mongoNotation Mongo notation
     * @return string
     */
    protected function compileMongoToSql($mongoNotation)
    {
        if (isset($mongoNotation['$or'])) {
            $ret = '';
            foreach ($mongoNotation['$or'] as $or) {
                $ret .= ($ret !== '' ? ' OR ' : '') . '(' . $this->compileMongoToSql($or) . ')';
            }
            return $ret;
        }
        if (isset($mongoNotation['$and'])) {
            $ret = '';
            foreach ($mongoNotation['$and'] as $and) {
                $ret .= ($ret !== '' ? ' AND ' : '') . '(' . $this->compileMongoToSql($and) . ')';
            }
            return $ret;
        }
        $ret = '';
        foreach ($mongoNotation as $key => $val) {
            if (!is_array($val)) {
                if (is_scalar($val)) {
                    $this->values[] = $val;
                    $val = new \Zer0\Model\Subtypes\Placeholder(count($this->values) - 1);
                }
                $ret .= ($ret !== '' ? ' AND ' : '') . $key . ' = ' . $val;
            } elseif (isset($val['$eq'])) {
                if (is_scalar($val['$eq'])) {
                    $this->values[] = $val['$eq'];
                    $val = new \Zer0\Model\Subtypes\Placeholder(count($this->values) - 1);
                }
                $ret .= ($ret !== '' ? ' AND ' : '') . $key . ' = ' . $val;
            } elseif (isset($val['$in'])) {
                if (is_array($val['$in'])) {
                    $this->values[] = $val['$in'];
                    $val = new \Zer0\Model\Subtypes\Placeholder(count($this->values) - 1);
                }
                $ret .= ($ret !== '' ? ' AND ' : '') . $key . ' IN(' . $val . ')';
            } elseif (isset($val['$nin'])) {
                if (is_array($val['$nin'])) {
                    $this->values[] = $val['$in'];
                    $val = new \Zer0\Model\Subtypes\Placeholder(count($this->values) - 1);
                }
                $ret .= ($ret !== '' ? ' AND ' : '') . $key . ' NOT IN(' . $val . ')';
            }
            // @TODO: add missing $ operators
        }
        return $ret;
    }

    /**
     * Delete objects matching current condition. Iterate across defined storages and call delete() on each.
     * @param callable $cb = null Callback
     *
     * @return ?integer Number of deleted objects (i.e. # affected)
     * @throws \Exception
     */
    public function delete($cb = null)
    {
        if ($cb !== null) {
            $cj = new ComplexJob(function ($cj) use ($cb) {
                $cb(max($cj->results));
            });
            foreach ($this->model->storages(true) as $k => $storage) {
                $cj($k, function ($k, $cj) use ($storage) {
                    $storage->delete($this, function ($affected) use ($k, $cj) {
                        $cj[$k] = $affected;
                    });
                });
            }
            $cj();
            return null;
        }
        $affected = 0;
        foreach ($this->model->storages(true) as $storage) {
            $affected = max($affected, $storage->delete($this));
        }
        return $affected;
    }

    /**
     * Check if any objects matching current condition exist
     *
     * @return boolean
     */
    public function exists()
    {
        return $this->count(1) > 0;
    }

    /**
     * Count number of objects, calling each storage's count() method until data is retrieved.
     *
     * @param  integer $limit = null [description]
     * @param  array $innerJoins = []
     * @param  \Zer0\Model\Expressions\GroupByClause $groupBy (optional) GroupBy
     * @param  callable $cb = null Callback
     * @return integer|null
     */
    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function count($limit = null, $innerJoins = [], $groupBy = null, $cb = null)
    {
        if ($cb !== null) { // If $cb is given, do it asynchronously
            $cj =
                new ComplexJob(function ($cj) use ($cb) { // This callback gets called when all tasks are done
                    if (isset($cj->vars['result'])) {
                        $result = $cj->vars['result'];
                    } else {
                        $result = null;
                    }
                    $cb($result);
                });
            $cj->maxConcurrency(1)// Only one task shall be running simultaneously.
            ->more(function ($cj) use ($groupBy, $limit, $innerJoins) {
                // This callback is called when existing tasks finish.

                // Iterating over indexes
                foreach ($this->model->indexes() as $k => $index) {
                    if (isset($cj->vars['result'])) {
                        break;
                    }
                    // Adding new task
                    yield $k => function ($jobname, $cj) use ($index, $groupBy, $limit, $innerJoins) {
                        $index->count(
                            $this,
                            $limit,
                            $innerJoins,
                            $groupBy,
                            function ($result) use ($jobname, $cj) {
                                if ($result !== null) {
                                    $cj->vars['result'] = $result;
                                }
                                $cj[$jobname] = true;
                            }
                        );
                    };
                }

                // Iterating over storages
                foreach ($this->model->storages() as $k => $storage) {
                    if (isset($cj->vars['result'])) {
                        break;
                    }
                    // Adding new task
                    yield $k => function ($jobname, $cj) use (
                        $limit,
                        $innerJoins,
                        $groupBy,
                        $storage
                    ) {
                        $storage->count(
                            $this,
                            $limit,
                            $innerJoins,
                            $groupBy,
                            function ($result) use ($cj, $jobname, $storage) {
                                if ($result !== null) {
                                    $cj->vars['result'] = $result;
                                }
                                $cj[$jobname] = true;
                            }
                        );
                    };
                }
            });
            $cj();
            return null; // Stop here
        }
        foreach ($this->model->indexes() as $index) {
            if (($n = $index->count($this, $limit, $innerJoins, $groupBy)) !== null) {
                return $n;
            }
        }
        foreach ($this->model->storages() as $storage) {
            if (($n = $storage->count($this, $limit, $innerJoins, $groupBy)) !== null) {
                return $n;
            }
        }
        return null;
    }

    /**
     * Fetch objects from storage, calling each storage's fetch() method until data is retrieved.
     *
     * @param  array $fields = [] Fields to fetch
     * @param  \Zer0\Model\Expressions\OrderByClause $orderBy = null Order by
     * @param  integer $offset = 0 Offset
     * @param  integer $limit = null Limit
     * @param  array $innerJoins (optional)
     * @param  \Zer0\Model\Expressions\GroupByClause $groupBy = null GroupBy
     * @param  callable $cb = null Callback
     *
     * @return array|null
     */
    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function fetch(
        $fields = [],
        $orderBy = null,
        $offset = 0,
        $limit = null,
        $innerJoins = [],
        $groupBy = null,
        $cb = null
    ) {
        if ($cb !== null) { // If $cb argument is given, fetch data asynchronously
            $modelClass = $this->modelClass;
            $checkFields = $fields ? array_flip($fields) : array_keys($modelClass::rules());

            // Creating a ComplexJob for indexes
            $cj = new ComplexJob(function ($cj) use (
                &$result,
                $fields,
                $offset,
                $limit,
                $orderBy,
                $innerJoins,
                $groupBy,
                $cb
            ) {
                // This callback gets called when all tasks are finished
                if (isset($cj->vars['result'])) {
                    $result = $cj->vars['result'];
                    $modelClass = $this->modelClass;
                    if (!count($result)) { // If $result is empty
                        $cb(new ResultList($result));
                        return;
                    }
                    $pk = $modelClass::primaryKeyScheme();
                    if ($pk !== null) {
                        $values = [];
                        foreach ($result as $obj) {
                            $values[] = $obj[$pk];
                        }
                        $this->model->condition('PrimaryKeyIn', $pk, $values)->fetch(
                            $fields,
                            $orderBy,
                            null,
                            null,
                            $innerJoins,
                            $groupBy,
                            $cb
                        );
                        return;
                    }
                }

                // Creating the second ComplexJob for storages.
                $cj = new ComplexJob(function ($cj) use ($cb) {
                    if (isset($cj->vars['result'])) {
                        $result = $cj->vars['result'];
                    } else {
                        $result = [];
                    }
                    $cb(new ResultList($result));
                });

                $cj->maxConcurrency(1)->more(
                // This callback is called when existing tasks finish.
                    function ($cj) use ($fields, $orderBy, $offset, $limit, $innerJoins, $groupBy) {
                        // Iterating over storages
                        foreach ($this->model->storages() as $k => $storage) {
                            if (isset($cj->vars['result'])) {
                                break;
                            }
                            // Adding new job
                            yield $k => function ($jobname, $cj) use (
                                $storage,
                                $fields,
                                $orderBy,
                                $offset,
                                $limit,
                                $innerJoins,
                                $groupBy
                            ) {
                                $storage->fetch(
                                    $this,
                                    $fields,
                                    $orderBy,
                                    $offset,
                                    $limit,
                                    $innerJoins,
                                    $groupBy,
                                    function ($result) use ($jobname, $cj) {
                                        if ($result !== null) {
                                            $cj->vars['result'] = $result;
                                        }
                                        $cj[$jobname] = true;
                                    }
                                );
                            };
                        }
                    }
                );
                $cj(); //
            });

            $cj->maxConcurrency(1)->more(
            // This callback is called when existing tasks finish.
                function ($cj) use ($fields, $orderBy, $groupBy, $checkFields, $offset, $limit, $innerJoins) {

                    // Iterating over indexes
                    foreach ($this->model->indexes() as $k => $index) {
                        if (isset($cj->vars['result'])) {
                            break;
                        }
                        yield $k => function ($jobname, $cj) use (
                            $index,
                            $fields,
                            $orderBy,
                            $groupBy,
                            $offset,
                            $limit,
                            $innerJoins
                        ) {
                            $index->fetch(
                                $this,
                                $fields,
                                $orderBy,
                                $offset,
                                $limit,
                                $innerJoins,
                                $groupBy,
                                function ($result) use ($jobname, $cj) {
                                    if ($result !== null) {
                                        $cj->vars['result'] = $result;
                                    }
                                    $cj[$jobname] = true;
                                }
                            );
                        };
                    }

                    // Iterating over storages
                    foreach ($this->model->storages() as $k => $storage) {
                        if (isset($cj->vars['result'])) {
                            break;
                        }
                        yield $k => function ($jobname, $cj) use (
                            $fields,
                            $orderBy,
                            $offset,
                            $limit,
                            $innerJoins,
                            $groupBy,
                            $storage,
                            $checkFields
                        ) {
                            $storage->fetch(
                                $this,
                                $fields,
                                $orderBy,
                                $offset,
                                $limit,
                                $innerJoins,
                                $groupBy,
                                function ($objects) use ($cj, $jobname, $storage, $checkFields) {
                                    if ($objects) {
                                        foreach ($objects as $i => $obj) {
                                            if (array_diff_key($checkFields, $obj)) {
                                                $objects = null;
                                                break;
                                            }
                                        }
                                    }
                                    $cj->vars['result'] = $objects;
                                    $cj[$jobname] = true;
                                }
                            );
                        };
                    }
                }
            );
            $cj();
            return null;
        }
        $cond = $this;
        $result = null;
        // @TODO: multi-index queries support
        foreach ($this->model->indexes() as $index) {
            if (($result = $index->fetch($this, $fields, $orderBy, $offset, $limit, $innerJoins)) !== null) {
                break;
            }
        }
        if ($result !== null) {
            $modelClass = $this->modelClass;
            $checkFields = $fields ? array_flip($fields) : array_keys($modelClass::rules());
            if (!count($result)) {
                return new ResultList($result);
            }
            foreach ($result as $obj) {
                if (!array_diff_key($checkFields, $obj)) {
                    return new ResultList($result);
                }
                break;
            }
            $pk = $modelClass::primaryKeyScheme();
            if ($pk !== null) {
                $values = [];
                foreach ($result as $obj) {
                    $values[] = $obj[$pk];
                }
                return $this->model->condition('PrimaryKeyIn', $pk, $values)->fetch(
                    $fields,
                    $orderBy,
                    0,
                    null,
                    $innerJoins,
                    $groupBy
                );
            }
        }

        // Iterating over storages
        foreach ($this->model->storages() as $storage) {
            if (!$storage instanceof ReadableInterface) {
                continue;
            }
            if (($result = $storage->fetch($cond, $fields, $orderBy, $offset, $limit, $innerJoins)) !==
                null
            ) {
                $result = new ResultList($result);
                if ($cb !== null) {
                    $cb($result);
                    return;
                }
                return $result;
            }
        }
        if ($cb !== null) {
            $cb(new ResultList([]));
            return null;
        }
        return new ResultList([]);
    }

    /**
     * Apply mongo notation on data and return result as boolean (passed or not)
     *
     * @param array $data
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function checkCondition(array $data)
    {
        $assert = true;

        foreach ($this->getMongoNotation() as $left => $operation) {
            if (is_array($operation)) {
                switch ($left) {
                    case '$or':
                        foreach ($operation as $right) {
                            $field = key($right);
                            $rightOperator = current($right);
                            $assert = $this->assertOperator(
                                key($rightOperator),
                                $data[$field],
                                current($rightOperator)
                            );
                            if ($assert) {
                                return true;
                            }
                        }
                        break;
                    case '$and':
                        foreach ($operation as $right) {
                            $field = key($right);
                            $rightOperator = current($right);
                            $assert &= $this->assertOperator(
                                key($rightOperator),
                                $data[$field],
                                current($rightOperator)
                            );
                        }
                        break;
                }

                foreach ($operation as $type => $right) {
                    if (!isset($data[$left])) {
                        $assert = false;
                        break;
                    }

                    $assert = $this->assertOperator($type, $data[$left], $right);
                    continue 2;
                }
            } else {
                if (!isset($data[$left])) {
                    $assert = false;
                } else {
                    $assert = $data[$left] == $operation;
                }
            }
        }

        return $assert;
    }

    /**
     * @param string $operator
     * @param mixed $value
     * @param mixed $literal
     * @return bool
     * @throws \InvalidArgumentException
     */
    protected function assertOperator($operator, $value, $literal)
    {
        switch ($operator) {
            case '$ne':
                return $value != $literal;
            case '$gt':
                return $value > $literal;
            case '$gte':
                return $value >= $literal;
            case '$lt':
                return $value < $literal;
            case '$lte':
                return $value <= $literal;
            case '$in':
                return in_array($value, $literal);
        }

        throw new InvalidArgumentException('Not supported operator: ' . $operator);
    }
}
