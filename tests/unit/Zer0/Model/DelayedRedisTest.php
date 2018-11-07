<?php

namespace Zer0\Model;

use Model\User;
use Zer0\TestCase;

/**
 * Class DelayedRedisTest
 * @package Zer0\Model
 */
class DelayedRedisTest extends TestCase
{
    use \Tests\UserSupport;

    /**
     *
     */
    public function setUp()
    {
        $this->markTestSkipped();
    }

    public function testDelayedUpdate()
    {
        $user = User::whereFieldEq('id', $this->user->getId())->load();
        $userClone = clone $user;
        $userClone->touchLast_login()->delayed(1)->save();
        $lastLogin = $userClone->last_login;
        $user->load();
        parent::assertEquals($lastLogin, $user->last_login, 'last_login is wrong in Redis');
        // Wait for the delayed operation
        sleep(2);
        $user->options(['storages' => ['SQL' => true]]);
        $user->load();
        parent::assertEquals($lastLogin, $user->last_login, 'last_login is wrong in SQL');
    }
}
