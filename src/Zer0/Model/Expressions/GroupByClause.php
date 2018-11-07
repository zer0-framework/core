<?php

namespace Zer0\Model\Expressions;

/**
 * Class GroupByClause
 * @package Zer0\Model\Expressions
 */
class GroupByClause extends Generic
{
    protected $mongoNotation;

    /**
     * Returns GroupByClause object
     * @param  array $orderBy Hash of fields
     * @param  string $modelClass Model class name
     * @return GroupByClause
     */
    public static function fromArray($orderBy, $modelClass)
    {
        $rules = $modelClass::rules();
        $clause = '';
        foreach ($orderBy as $field) {
            if (!isset($rules[$field])) {
                continue; // @TODO: throw an exception?
            }
            $clause .= ($clause !== '' ? ', ' : '') . $field;
        }
        if ($clause === '') {
            return null;
        }
        return new static($clause, null, $modelClass);
    }

    /**
     * __get
     * @param  string $prop Property name
     * @return mixed
     */
    public function __get($prop)
    {
        if ($prop === 'mongoNotation') {
            $mongoNotation = [];
            foreach (explode(',', $this->expr) as $field) {
                $field = trim($field);
                $mongoNotation[$field] = 1;
            }
            return $this->mongoNotation = $mongoNotation;
        }
        return $this->{$prop};
    }
}
