<?php
declare(strict_types=1);

namespace Zer0\Cache;

use Zer0\Cache\Item\Item;
use Zer0\Cache\Pools\Base;
use Zer0\Cache\Pools\Redis as RedisPool;
use Zer0\Config\Inline;
use Zer0\TestCase;

/**
 * Class CacheTest
 * @package Zer0\Cache
 */
final class CacheTest extends TestCase
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
                'prefix' => 'phpunit:',
            ]
        ), $this->app);
    }

    public function testSimple(): void
    {
        $item = $this->pool->item('test-item');

        self::assertInstanceOf(Item::class, $item);

        $item
            ->expiresAfter(10)
            ->set('foo')
            ->save();

        $item = $this->pool->item('test-item');
        self::assertEquals('foo', $item->get());
    }

    public function testWithTags(): void
    {
        $item = $this->pool->item('tagged-item');

        self::assertInstanceOf(Item::class, $item);

        $item
            ->expiresAfter(10)
            ->set('foo')
            ->addTag('some-tag')
            ->save();

        $item = $this->pool->item('tagged-item');
        self::assertEquals('foo', $item->get());

        $this->pool->invalidateTag('some-tag');

        $item = $this->pool->item('tagged-item');
        self::assertNull($item->get());
    }
}
