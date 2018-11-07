<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class RedisAsync
 * @package Zer0\Brokers
 */
class RedisAsync extends Base
{
    /**
     * @var string
     */
    protected $broker = 'Redis';

    /**
     * @param ConfigInterface $config
     * @return \PHPDaemon\Clients\Redis\Pool
     */
    public function instantiate(ConfigInterface $config): \PHPDaemon\Clients\Redis\Pool
    {
        return \PHPDaemon\Clients\Redis\Pool::getInstance(['servers' => 'tcp://' . $config->server]);
    }
}
