<?php

namespace Zer0\Brokers;

use PHPDaemon\Core\ClassFinder;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class CachePoolAsync
 * @package Zer0\Brokers
 */
class CachePoolAsync extends Base
{
    /**
     * @var string
     */
    protected $broker = 'CachePool';

    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        $class = ClassFinder::find($config->type . 'Async', ClassFinder::getNamespace(\Zer0\Cache\Pools\BaseAsync::class), '~');
        return new $class($config, $this->app);
    }
}
