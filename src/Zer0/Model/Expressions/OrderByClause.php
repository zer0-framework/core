<?php

namespace Zer0\Model\Expressions;

use Zer0\Model\Exceptions\UnknownFieldException;

/**
 * Class OrderByClause
 * @package Zer0\Model\Expressions
 */
class OrderByClause extends Generic
{
    protected $mongoNotation;

    /**
     * Returns OrderByClause object
     * @param  array $orderBy Hash of fields
     * @param  string $modelClass Model class name
     * @return OrderByClause
     * @throws UnknownFieldException
     */
    public static function fromArray($orderBy, $modelClass)
    {
        $rules = $modelClass::rules();
        $clause = '';

        foreach ($orderBy as $field => $dir) {
            if ($dir === 1) {
                $dir = 'ASC';
            } elseif ($dir === -1) {
                $dir = 'DESC';
            } elseif ($dir !== 'ASC' && $dir !== 'DESC') {
                $dir = 'ASC';
            }

            if (!isset($rules[$field])) {
                throw new UnknownFieldException("Field '$field' could not be found");
            }

            $clause .= ($clause !== '' ? ', ' : '') . $field . ' ' . $dir;
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
            $order = [];
            foreach (explode(',', $this->expr) as $part) {
                list($field, $dir) = explode(' ', ltrim($part) . ' ');
                $order[$field] = strtoupper($dir) === 'DESC' ? -1 : 1;
            }
            return $this->mongoNotation = $order;
        }
        return $this->{$prop};
    }
}
