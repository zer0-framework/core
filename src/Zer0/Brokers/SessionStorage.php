<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class SessionStorage
 * @package Zer0\Brokers
 */
class SessionStorage extends Base
{
    /**
     * @param ConfigInterface $config
     * @return \Zer0\Session\Storages\Base
     */
    public function instantiate(ConfigInterface $config)
    {
        $split = explode('\\', \Zer0\Session\Storages\Base::class);
        $split[count($split) - 1] = $config->type;
        $class = implode('\\', $split);
        return new $class($this->app, $config);
    }
}
