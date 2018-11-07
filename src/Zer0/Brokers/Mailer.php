<?php

namespace Zer0\Brokers;

use PHPDaemon\Core\ClassFinder;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Mailer
 * @package Zer0\Brokers
 */
class Mailer extends Base
{

    /**
     * @param ConfigInterface $config
     * @return \Zer0\Mailer\Base
     */
    public function instantiate(ConfigInterface $config): \Zer0\Mailer\Base
    {
        $class = ClassFinder::find($config->type ?? 'Plain', ClassFinder::getNamespace(\Zer0\Mailer\Base::class), '~');
        return new $class($config);
    }
}
