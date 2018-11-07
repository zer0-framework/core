<?php
declare(strict_types=1);

namespace Zer0\Queue;

use RedisClient\RedisClient;
use Zer0\Config\Inline;
use Zer0\Queue\Pools\Base;
use Zer0\Queue\Pools\Redis as RedisPool;
use Zer0\TestCase;

/**
 * Class QueueTest
 * @package Zer0\Queue
 */
final class QueueTest extends TestCase
{

    /**
     * @var Base
     */
    protected $pool;

    /**
     * This method is called before each test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->pool = new RedisPool(new Inline(
            [
                'prefix' => 'phpunit:queue',
            ]
        ), $this->app);

        /**
         * @var RedisClient $redis
         */
        $redis = $this->app->broker('Redis')->get();
        $redis->eval("local keys=redis.call('keys', ARGV[1]);
        if table.getn(keys) > 0
            then
                return redis.call('del', unpack(keys))
            else
                return 1
        end", [], ['phpunit:queue:*']);
    }

    public function testSimple(): void
    {
        $task = new SomeTask();

        $task->setChannel('phpunit');

        $this->pool->enqueue($task);

        self::assertInternalType('string', $task->getId());

        unset($task);

        $task = $this->pool->poll();

        self::assertEquals('phpunit', $task->getChannel());

        self::assertInstanceOf(SomeTask::class, $task);

        $task->setCallback(function (TaskAbstract $task) {
            $this->pool->complete($task);
        })->execute();
    }
}
