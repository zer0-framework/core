<?php

namespace Zer0\Brokers;

use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class PDOAsync
 * @package Zer0\Brokers
 */
class PDOAsync extends Base
{
    /**
     * @var string
     */
    protected $broker = 'PDO';

    /**
     * @param ConfigInterface $config
     * @return null|object
     */
    public function instantiate(ConfigInterface $config)
    {
        foreach ($config->dsn as $driver => $dsn) {
            if ($driver === 'mysql') {
                return \PHPDaemon\Clients\MySQL\Pool::getInstance([
                    'server' => 'tcp://' . $dsn['user'] . ':' . $config->password
                        . '@' . $dsn['host'] . '/' . $dsn['dbname'],
                    'maxconnperserv' => 4,
                ]);
            } elseif ($driver === 'pgsql') {
                return \PHPDaemon\Clients\PostgreSQL\Pool::getInstance([
                    'server' => 'tcp://' . $dsn['user'] . ':' . $config->password
                        . '@' . $dsn['host'] . ':' . ($dsn['port'] ?? '5432') . '/' . $dsn['dbname'],
                    'maxconnperserv' => 4,
                ]);
            }
        }
    }
}
