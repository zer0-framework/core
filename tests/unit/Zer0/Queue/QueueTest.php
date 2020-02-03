<?php
declare(strict_types=1);

namespace Zer0\Queue;

use Zer0\Config\Inline;
use Zer0\Queue\Pools\Base;
use Zer0\TestCase;
use Zer0\Queue\Pools\Redis as RedisPool;

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
    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = new RedisPool(new Inline(
            [
                'prefix' => 'phpunit:queue',
            ]
        ), $this->app);
    }

    public function testSimple(): void
    {
        $task = new SomeTask();

        $this->pool->enqueue($task);

        self::assertIsString($task->getId());

        $task = $this->pool->poll([$task->getChannel()]);

        self::assertInstanceOf(SomeTask::class, $task);

        $task->setCallback(function (TaskAbstract $task) {
            $this->pool->complete($task);
        })->execute();
    }

    public function testThen(): void
    {
        $pool = $this->app->factory('Queue');

        $task = new SomeTask('foo');

        $taskTwo = new AnotherTask();

        $pool->assignId($taskTwo);

        $task->then($taskTwo);

        $pool->enqueue($task);

        $taskTwo = $pool->wait($taskTwo);

        self::assertEquals('oof :-)', $taskTwo->getStr());
    }


}
