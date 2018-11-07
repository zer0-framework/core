<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class SessionAsync
 * @package Zer0\Brokers
 */
class SessionAsync extends Base
{
    /**
     * @var string
     */
    protected $broker = 'Session';

    /**
     * @var bool
     */
    protected $caching = false;

    /**
     * @param ConfigInterface $config
     * @return \Zer0\Session\SessionAsync
     */
    public function instantiate(ConfigInterface $config): \Zer0\Session\SessionAsync
    {
        if (isset($_SESSION) && $_SESSION instanceof \Zer0\Session\Session) {
            return $_SESSION;
        }
        return new \Zer0\Session\SessionAsync(
            $config,
            $this->app->broker('SessionStorage')->get($config->storageAsync),
            $this->app->broker('HTTP')->get($config->http ?? '')
        );
    }
}
