<?php

namespace Zer0\Model\Indexes;

use Zer0\TestCase;

/**
 * Class RedisZSetTest
 * @package Zer0\Model\Indexes
 */
class RedisZSetTest extends TestCase
{
    use \Tests\UserSupport;

    /**
     *
     */
    public function setUp()
    {
        $this->markTestSkipped();
    }

    /**
     * @test
     * @covers \Zer0\Model\Indexes\RedisZSet->fetch()
     */
    public function testValueIn()
    {
        $this->user->touchLast_login()->save();
        $users =
            \Model\User::where('online = 1 AND id = ?', [$this->user->getId()])
                ->orderBy('last_login', 'ASC')
                ->load(10)
                ->toArray();
        parent::assertEquals(1, count($users));
        $this->assertArrayHasKey('last_login', $users[0]);
        $this->assertArrayHasKey('username', $users[0]);
        ;

        $users =
            \Model\User::where('online = 1 AND id IN (?)', [$this->user->getId(), '123'])
                ->orderBy('last_login', 'ASC')
                ->load(10)
                ->toArray();
        parent::assertEquals(1, count($users));
        $this->assertArrayHasKey('last_login', $users[0]);
        $this->assertArrayHasKey('username', $users[0]);
        ;
    }

    public function testScoreRange()
    {
        $this->user->touchLast_login()->save();
        $users =
            \Model\User::where('online = 1 AND last_login > ?', [time() - 60])
                ->orderBy('last_login', 'ASC')
                ->load(1)
                ->toArray();

        parent::assertEquals(1, count($users));
        $this->assertArrayHasKey('last_login', $users[0]);
        $this->assertArrayHasKey('username', $users[0]);
    }
}
