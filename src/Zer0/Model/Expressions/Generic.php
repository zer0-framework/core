<?php

namespace Zer0\Model\Expressions;

/**
 * Class Generic
 * Base class for SQL-type expressions (e.g.
 *
 * @package Zer0\Model\Expressions
 */
class Generic
{
    /** @var string Expression */
    protected $expr;

    /** @var array Values */
    protected $values;

    /** @var \Zer0\Model\Generic Associated \Zer0\Model\Generic object */
    protected $model;

    /** @var string Associated \Zer0\Model\Generic class name */
    protected $modelClass;

    /**
     * Constructor
     *
     * @param string $expr
     * @param array $values
     * @param \Zer0\Model\Generic $model
     */
    public function __construct($expr, $values, $model)
    {
        $this->expr = $expr;
        $this->values = $values;
        $this->modelClass = $model !== null ? get_class($model) : null;
        $this->model = $model;
        $this->init();
    }

    /**
     * Initializer called from constructor
     *
     * @return void
     */
    public function init()
    {
        if (!is_array($this->values)) {
            $this->values = [$this->values];
        }
    }

    /**
     * Returns $values
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Sets $values
     * @param array $values
     * @return void
     */
    public function setValues($values)
    {
        $this->values = $values;
    }

    /**
     * @param  string $prop Property name
     * @return mixed
     */
    public function __get($prop)
    {
        return $this->{$prop};
    }

    /**
     * __toString
     * @return string $this->expr
     */
    public function __toString()
    {
        return (string)$this->expr;
    }
}
