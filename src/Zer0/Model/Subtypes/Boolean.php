<?php

namespace Zer0\Model\Subtypes;

use Zer0\Drivers\PDO\PDO;

/**
 * Class Timestamp
 *
 * @package Zer0\Model\Subtypes
 */
class Boolean
{
    protected $value;

    /**
     * Constructor
     * @param mixed $value Boolean
     */
    public function __construct($value = null)
    {
        $this->value = (bool)$value;
    }

    /**
     * __toString
     * @return string
     */
    public function __toString()
    {
        return $this->value ? 'true' : false;
    }

    /**
     * @return bool
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param \Zer0\Drivers\PDO\PDO $pdo
     * @return false|string
     */
    public function toString(PDO $pdo)
    {
        return $this->value ? 'true' : 'false';
    }
}
