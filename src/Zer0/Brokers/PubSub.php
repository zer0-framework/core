<?php

namespace Zer0\Brokers;

use PHPDaemon\Core\ClassFinder;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class PubSub
 * @package Zer0\Brokers
 */
class PubSub extends Base
{
    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        $class = ClassFinder::find($config->type, ClassFinder::getNamespace(\Zer0\PubSub\Pools\Base::class), '~');
        return new $class($config, $this->app);
    }
}
