<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class CSRF_Token
 * @package Zer0\Brokers
 */
class HTMLPurifier extends Base
{

    /**
     * @param ConfigInterface $config
     * @return \HTMLPurifier
     */
    public function instantiate(ConfigInterface $config): \HTMLPurifier
    {
        $config = \HTMLPurifier_Config::createDefault();
        $html_purifier_cache_dir = sys_get_temp_dir() . '/HTMLPurifier/DefinitionCache';
        if (!is_dir($html_purifier_cache_dir)) {
            mkdir($html_purifier_cache_dir, 0770, true);
        }
        $config->set('Cache.SerializerPath', $html_purifier_cache_dir);
        return new \HTMLPurifier($config);
    }
}
