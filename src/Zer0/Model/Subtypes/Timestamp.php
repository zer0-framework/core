<?php

namespace Zer0\Model\Subtypes;

use Zer0\Drivers\PDO\PDO;

/**
 * Class Timestamp
 *
 * @package Zer0\Model\Subtypes
 */
class Timestamp
{
    protected $unix;

    /**
     * Constructor
     * @param string $value Timestamp
     */
    public function __construct($value = null)
    {
        if ($value === null) {
            $value = time();
        }
        $this->unix = is_int($value) ? $value : strtotime($value);
    }

    /**
     * __toString
     * @return string
     */
    public function __toString()
    {
        return date('Y-m-d H:i:s', $this->unix);
    }

    /**
     * @param \Zer0\Drivers\PDO\PDO $pdo
     * @return false|string
     */
    public function toString(PDO $pdo)
    {
        return $pdo->quote(date('Y-m-d H:i:se', $this->unix));
    }
}
