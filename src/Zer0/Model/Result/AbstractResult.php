<?php

namespace Zer0\Model\Result;

/**
 * Class Generic
 */
abstract class AbstractResult
{
    /**
     * Objects
     * @var array
     */
    public $objects = [];

    /**
     * Constructor
     *
     * @param $objects
     */
    public function __construct($objects)
    {
        $this->objects = $objects;
    }
}
