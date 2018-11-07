<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Socket
 * @package Zer0\Brokers
 */
class Socket extends Base
{
    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        return new class($config) {
            /**
             * @var ConfigInterface
             */
            public $config;

            /**
             *  Constructor.
             * @param ConfigInterface $config
             */
            public function __construct(ConfigInterface $config)
            {
                $this->config = $config;
            }
        };
    }
}
