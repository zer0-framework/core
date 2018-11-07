<?php

namespace Zer0\Brokers;

use PHPDaemon\Core\ClassFinder;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class QueueAsync
 * @package Zer0\Brokers
 */
class QueueAsync extends Base
{
    /**
     * @var string
     */
    protected $broker = 'Queue';

    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        $class = ClassFinder::find($config->type . 'Async', ClassFinder::getNamespace(\Zer0\Queue\Pools\BaseAsync::class), '~');
        return new $class($config, $this->app);
    }
}
