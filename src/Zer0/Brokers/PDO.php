<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Drivers\PDO\Tracy\BarPanel;

/**
 * Class PDO
 * @package Zer0\Brokers
 */
class PDO extends Base
{
    /**
     * @param ConfigInterface $config
     * @return \Zer0\Drivers\PDO\PDO
     */
    public function instantiate(ConfigInterface $config): \Zer0\Drivers\PDO\PDO
    {
        $tracy = $this->app->broker('Tracy')->get();
        $pdo = new \Zer0\Drivers\PDO\PDO(
            self::getDSN($config->dsn),
            $config->username ?? null,
            $config->password ?? null,
            $config->options ?? [],
            $tracy !== null
        );

        if ($tracy !== null) {
            $panel = new BarPanel($pdo);
            $panel->title = 'PDO (' . $this->lastName . ')';
            $tracy->addPanel($panel);
            $this->app->broker('HTTP')->get()->on('endRequest', function () use ($pdo) {
                $pdo->resetQueryLog();
            });
        }

        return $pdo;
    }

    /**
     * @param mixed $dsn
     * @return string
     */
    protected static function getDSN($dsn): string
    {
        if (is_string($dsn)) {
            return $dsn;
        }
        $ret = '';
        foreach ($dsn as $type => $sub) {
            $ret .= $type . ':';
            foreach ($sub as $key => $value) {
                $ret .= $key . '=' . $value . ';';
            }
        }
        return $ret;
    }
}
