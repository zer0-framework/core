<?php

namespace Zer0\Model\Expressions\Conditions;

/**
 * 'field IN(?)'
 */
class In extends Generic
{
    /**
     * Field to match
     * @var string
     */
    protected $field;

    /**
     * Array of values to match against
     * @var array
     */
    protected $in;

    /**
     * Constructor expects $this->expr to be a field name and $this->values to contain value(s) to match against.
     * {$this->expr} IN ({$this->values}) = some_field IN (val1, val2, ...)
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->field = $this->expr;
        $this->in = $this->values;

        // Transform $this->expr into a placeholder format
        $this->expr = $this->field . ' = ?';
        if (is_array($this->in)) {
            $this->in = array_values($this->in);
        }
        $this->values = [&$this->in];
    }
}
