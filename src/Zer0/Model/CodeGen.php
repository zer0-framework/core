<?php

namespace Zer0\Model;

/**
 * Used for SQL2PHP code generation.
 */
class CodeGen
{


    /**
     * Executes transformed SQL
     * See transformSqlToPhp()
     *
     * @param  string $query SQL query
     * @param  array $options [option => value, ...]
     * @return object Model object or Iterator
     * @throws \Exception
     */
    public static function execute($query, $options = [])
    {
        $options['addNamespaces'] = true;
        return eval('return ' . static::transformSqlToPhp($query, $options) . ';');
    }

    /**
     * Transforms SQL query to PHP Code. Note that the relation names in the queries must
     * refer to Model class names, *not* database tables. e.g., "FROM User", not "FROM users".
     *
     * @example
     * Input:   ('SELECT Item.*, Category.name AS categoryName FROM Item'
     *           . ' LEFT JOIN Category ON Item.category = Category.id'
     *           . ' WHERE Item.id IN (?) AND Item.deleted = 0', ['addNamespaces' => true])
     * Output:  Model\Item::where('Item.id IN (?) AND Item.deleted = 0', [null])
     *              ->fields(['Item.*'])
     *              ->leftJoin('Category', 'Item.category = Category.id', ['categoryName' => 'Category.name'])
     *
     * @example
     * Input:   ('SELECT * FROM User INNER JOIN User~online WHERE id IN (?)', ['addNamespaces' => false])
     * Output:  User::where('id IN (?)', [null])
     *              ->innerJoin('User~online')
     *
     *
     * @param  string $query SQL query
     *              Supports SELECT, JOIN, WHERE, LIMIT, OFFSET
     *
     * @param  array $options [option => value, ...]
     *             Supported options:
     *                  addNamespaces => boolean (add namespaces to class names)
     * @return string PHP code
     * @throws \Exception
     */
    public static function transformSqlToPhp($query, $options = [])
    {
        isset($options['addNamespaces']) || $options['addNamespaces'] = true;
        $values = [];
        static::normalizeQuery($query, $values);

        if (!preg_match('~^(SELECT|UPDATE|INSERT|REPLACE|DELETE)\s~i', $query, $match)) {
            throw new \Exception('Unrecognized query (unknown type): ' . $query);
        }
        $type = strtoupper($match[1]);
        if ($type === 'SELECT') {
            return static::selectQuery($query, $values, $options);
        } else {
            throw new \Exception($type . ' queries are not yet supported by CodeGen: ' . $query);
        }
    }

    /**
     * Normalize query: extract strings, remove comments, remove whitespace from SQL query.
     *
     * @param  string &$query
     * @param  array &$values
     * @return void
     */
    protected static function normalizeQuery(&$query, &$values)
    {
        // Function to return character at position $pos.
        // $retNull indicates whether to return null or throw exception when $pos is out of bounds
        $getChar = function ($pos, $retNull = false) use (&$query) {
            if (!isset($query[$pos])) {
                if ($retNull) {
                    return null;
                }
                throw new \Exception('Unexpected end');
            }
            return $query[$pos];
        };

        // Tokenizer, in each iteration we get the next token
        $offset = 0;
        $startPos = null; // To suppress stupid PhpStorm error message
        static $tokens = ['/*', ':', '?', '"', '\''];
        while (true) {
            $token = null;
            foreach ($tokens as $t) {
                if (($pos = strpos($query, $t, $offset)) !== false) {
                    if ($token === null || $pos < $startPos) {
                        $token = $t;
                        $startPos = $pos;
                    }
                }
            }
            if ($token === null) {
                break;
            }
            $p = $startPos;
            if ($token === '?') { // Placeholder
                $values[] = null;
                $offset = $startPos + 1;
                continue;
            } elseif ($token === ':') { // Named placeholder
                $placeholder = null;
                while (++$p) {
                    $char = $getChar($p, true);
                    if ($char !== null && ctype_alnum($char)) {
                        $placeholder .= $char;
                    } else {
                        $values[$placeholder] = null;
                        $offset = $p;
                        break;
                    }
                }
            } elseif ($token === '"' || $token === '\'') { // Quoted string
                $str = '';
                while (++$p) {
                    $char = $getChar($p);
                    if ($char === $token) {
                        $query = substr_replace($query, $placeholder = '?', $startPos, $p - $startPos + 1);
                        $values[] = $str;
                        $offset = $startPos + strlen($placeholder);
                        break;
                    }
                    if ($char === '\\') {
                        $str .= $getChar(++$p);
                    } else {
                        $str .= $char;
                    }
                }
            } elseif ($token === '/*') { // /* Comment */
                $offset += 2;
                $pos = strpos($query, '*/', $offset);
                if ($pos === false) {
                    $pos = strlen($query);
                }
                $query = substr_replace($query, ' ', $startPos, $pos - $startPos);
            }
        }

        // Replace multiple spaces, tabs and new-line symbols with a single space
        $query = preg_replace('~\s+~', ' ', $query);
        $query = trim($query);
    }

    /**
     * Processes SELECT queries
     *
     * @param  string $query SQL query
     * @param  array $values Values for placeholders
     * @param  array $options
     * @return string PHP Code
     * @throws \Exception
     */
    protected static function selectQuery($query, $values, $options)
    {
        // This regex splits the SELECT query into parts
        if (!preg_match('~SELECT (.*?) FROM (\S+)'
            . '((?: (?:INNER|LEFT|LEFT GROUPED) JOIN \S+.*?)+)?'
            . '(?: WHERE (.*?))?'
            . '(?: LIMIT (.*?))?'
            . '(?: OFFSET (.*?))?'
            . '$~i', $query, $match)
        ) {
            throw new \Exception('Cannot parse the query');
        }
        $parts = [
            'FIELDS' => 1,
            'FROM' => 2,
            'JOINS' => 3,
            'WHERE' => 4,
            'LIMIT' => 5,
            'OFFSET' => 6,
        ];
        foreach ($parts as &$n) {
            if (isset($match[$n]) && strlen($match[$n])) {
                $n = $match[$n];
            } else {
                $n = null;
            }
        }

        $parts['WHERE'] = static::selectQueryWhere($parts['WHERE'], $values);

        $code = ($options['addNamespaces'] ? '\\Model\\' : '') . $parts['FROM']
            . '::where(' . var_export($parts['WHERE'], true)
            . ($values ? ', ' . static::genArray($values) : '') . ')';

        // SELECT <fields> clause, results in $fields and $joinedFields
        $fields = [];
        $joinedFields = [];
        if (isset($parts['FIELDS'])) {
            static::selectQueryFields($parts['FIELDS'], $fields, $joinedFields, $parts);
            if ($fields && $fields !== ['*']) {
                $code .= "\n->fields(" . static::genArray($fields) . ')';
            }
        }

        // JOIN clauses
        if (isset($parts['JOINS'])) {
            static::selectQueryJoins($parts['JOINS'], $code, $joinedFields);
        }

        // LIMIT and OFFSET clauses
        if (isset($parts['LIMIT']) || isset($parts['OFFSET'])) {
            $e = array_map('trim', explode(',', $parts['LIMIT']));
            if (count($e) > 1) {
                list($parts['OFFSET'], $parts['LIMIT']) = $e;
            }
            if (isset($parts['OFFSET'])) {
                $code .= "\n->offset(" . $parts['OFFSET'] . ')';
            }
            if (isset($parts['LIMIT'])) {
                $code .= "\n->load(" . $parts['LIMIT'] . ')';
            }
        }
        return $code;
    }

    /**
     * Handler of WHERE clause
     * @param string $where
     * @param array $values Placeholder values
     * @return mixed
     */
    protected static function selectQueryWhere($where, &$values)
    {
        // WHERE clause
        $i = 0;
        return preg_replace_callback('~\?|((\S+) = )(\?|:\w+)~', function ($match) use (&$values, &$i) {
            if ($match[0] === '?') {
                ++$i;
                return $match[0];
            }
            if ($match[3] === '?') {
                $field = $match[2];
                $values[$field] = $values[$i];
                unset($values[$i]);
                ++$i;
                return $match[1] . ':' . $field;
            } elseif ($match[3][0] === ':') {
                $field = substr($match[3], 1);
                if (!isset($values[$field])) {
                    $values[$field] = null;
                }
            }
            return $match[0];
        }, $where);
    }

    /**
     * Generates PHP representation of the given array
     * @param array $array
     * @return string [...]
     */
    protected static function genArray($array)
    {
        $str = '';
        foreach ($array as $k => $v) {
            if ($v === null) {
                $v = 'null';
            } else {
                $v = var_export($v, true);
            }
            $str .= ($str !== '' ? ', ' : '') . (!is_int($k) ? var_export($k, true) . ' => ' : '') . $v;
        }
        return '[' . $str . ']';
    }

    /**
     * Handler of FIELDS
     * @param $str
     * @param $fields
     * @param $joinedFields
     * @param $parts
     * @throws \Exception
     */
    protected static function selectQueryFields($str, &$fields, &$joinedFields, &$parts)
    {
        foreach (explode(',', $str) as $f) {
            // Dissect field definitions like 'foo AS bar', 'foo bar', 'foo'
            if (!preg_match('~^(\S+)(?:(?: AS)? (\S+))?$~i', trim($f), $match)) {
                throw new \Exception('Illegal syntax in a SELECT expression: ' . $f);
            }
            if (isset($match[2]) && strlen($match[2])) {
                $src = $match[1];
                $dst = $match[2];
                $e = explode('.', $src);
                if (count($e) === 1 || $e[0] === $parts['FROM']) {
                    $fields[$dst] = $src;
                } else {
                    $joinedFields[$e[0]] = [$dst => $src];
                }
            } else {
                $src = $match[1];
                $e = explode('.', $src);
                if (count($e) === 1 || $e[0] === $parts['FROM']) {
                    $fields[] = $src;
                } else {
                    $joinedFields[$e[0]] = [$e[1] => $src];
                }
            }
        }
    }

    /**
     * Handler of JOINS
     * @param $joins
     * @param &$code
     * @param &$joinedFields
     */
    protected static function selectQueryJoins($joins, &$code, $joinedFields)
    {
        preg_replace_callback('~(INNER|LEFT|LEFT GROUPED) JOIN (\S+)(?: ON (.*?))?'
            . '(?= (?:INNER|LEFT|LEFT GROUPED) JOIN|$)~i', function ($match) use (&$code, $joinedFields) {
                $joinType = $match[1];
                $model = $match[2];
                $on = isset($match[3]) ? $match[3] : null;
                $fields = isset($joinedFields[$model]) ? $joinedFields[$model] : [];
                $code .= "\n->" . strtolower($joinType)
                . 'Join(' . var_export($model, true)
                . ($on || $fields ? ', ' . var_export($on, true) : '')
                . ($fields ? ', ' . static::genArray($fields) : '')
                . ')';
            }, $joins);
    }
}
