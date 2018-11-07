<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Cli
 * @package Zer0\Brokers
 */
class Cli extends Base
{

    /**
     * @param ConfigInterface $config
     * @return \Zer0\Cli\Cli
     */
    public function instantiate(ConfigInterface $config): \Zer0\Cli\Cli
    {
        return new \Zer0\Cli\Cli($config, $this->app);
    }
}
