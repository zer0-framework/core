<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Autorun
 * @package Zer0\Brokers
 */
class Autorun extends Base
{
    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        foreach ($config->toArray() as $item) {
            if (is_string($item)) {
                $this->app->broker($item)->get();
            }
        }
        return new class {
        };
    }
}
