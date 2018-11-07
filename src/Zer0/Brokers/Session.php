<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Session
 * @package Zer0\Brokers
 */
class Session extends Base
{
    /**
     * @var bool
     */
    protected $caching = false;

    /**
     * @param ConfigInterface $config
     * @return \Zer0\Session\Session
     */
    public function instantiate(ConfigInterface $config): \Zer0\Session\Session
    {
        if (isset($_SESSION) && $_SESSION instanceof \Zer0\Session\Session) {
            return $_SESSION;
        }
        return new \Zer0\Session\Session(
            $config,
            $this->app->broker('SessionStorage')->get($config->storage ?? ''),
            $this->app->broker('HTTP')->get($config->http ?? '')
        );
    }
}
