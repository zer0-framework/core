<?php

namespace Zer0\Brokers;

use RedisClient\RedisClient;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Drivers\Redis\RedisDebug;
use Zer0\Drivers\Redis\Tracy\BarPanel;
use Zer0\Model\Exceptions\UnsupportedActionException;

/**
 * Class Redis
 * @package Zer0\Brokers
 */
class Redis extends Base
{
    /**
     * @param ConfigInterface $config
     * @return RedisClient
     * @throws UnsupportedActionException
     */
    public function instantiate(ConfigInterface $config)
    {
        $type = $config->type ?? 'standalone';
        if ($type === 'standalone') {
            $attrs = [
                'server' => ($config->server ?? '127.0.0.1') . ':' . ($config->port ?? 6379),
                'timeout' => $config->timeout ?? 30,
            ];
            $redis = new RedisClient($attrs);
        } else {
            throw new UnsupportedActionException;
        }


        $tracy = $this->app->broker('Tracy')->get();
        if ($tracy !== null) {
            $redis = new RedisDebug($redis);
            $tracy->addPanel(new BarPanel($redis));
            $this->app->broker('HTTP')->get()->on('endRequest', function () use ($redis) {
                $redis->resetQueryLog();
            });
        }

        return $redis;
    }
}
