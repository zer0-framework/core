<?php
declare(strict_types=1);

namespace Zer0\Queue;

use Zer0\App;
use Zer0\Queue\Exceptions\RuntimeException;
use Zer0\Queue\Exceptions\WaitTimeoutException;
use Zer0\Queue\Pools\RedisAsync;
use PHPDaemon\Clients\Redis\Connection as RedisConnection;

/**
 * Class SomeTask
 * @package Zer0\Queue
 */
final class SomeTask extends \Zer0\Queue\TaskAbstract
{
    /**
     * @var string
     */
    public $foo;

    /**
     * @var string
     */
    public $str;

    /**
     * SomeTask constructor.
     * @param string $str
     */
    public function __construct(string $str = '')
    {
        $this->str = $str;
    }

    /**
     * @return string
     */
    public function getStr(): string
    {
        return $this->str;
    }

    /**
     *
     */
    public function execute(): void
    {
        $this->foo = 'bar';
        $this->str = strrev($this->str);
        $this->log('some log message');

        if ($this->str === 'cba') {
            throw new RuntimeException('aaa');
        }

        $this->complete();
    }
}
