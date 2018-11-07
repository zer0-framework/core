<?php

namespace Zer0\Config\Interfaces;

/**
 * Interface ConfigInterface
 * @package Zer0\Config\Interfaces
 */
interface ConfigInterface
{
    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name);
}
