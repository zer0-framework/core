<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class CSRF_Token
 * @package Zer0\Brokers
 */
class CSRF_Token extends Base
{

    /**
     * @param ConfigInterface $config
     * @return \Zer0\Security\CSRF_Token
     */
    public function instantiate(ConfigInterface $config): \Zer0\Security\CSRF_Token
    {
        return new \Zer0\Security\CSRF_Token($config, $this->app->broker('HTTP')->get());
    }
}
