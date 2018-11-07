<?php

namespace Zer0\Config;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Inline
 * @package Zer0\Config
 */
class Inline implements ConfigInterface
{
    /**
     * Inline constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getValue(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     *
     */
    public function toArray()
    {
        return $this->data;
    }
}
