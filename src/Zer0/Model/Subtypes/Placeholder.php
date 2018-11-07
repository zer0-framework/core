<?php

namespace Zer0\Model\Subtypes;

/**
 * Class Placeholder
 * Used as a placeholder representationin mongo notation
 * @package Zer0\Model\Subtypes
 */
class Placeholder
{
    protected $name;

    /**
     * Constructor
     * @param string $name Placeholder name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * __toString
     * @return string Field name
     */
    public function __toString()
    {
        return ':' . $this->name;
    }

    /**
     * @param \PDO $pdo
     * @return false|string
     */
    public function toString(\PDO $pdo)
    {
        return ':' . $this->name;
    }
}
