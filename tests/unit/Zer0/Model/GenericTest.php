<?php

namespace Zer0\Model;

use Zer0\Helpers\Str;
use Model\User;
use Zer0\TestCase;

/**
 * Class GenericTest
 * @package Zer0\Model
 */
class GenericTest extends TestCase
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
     */
    public function testLteCondition()
    {
        parent::assertEquals(
            0,
            User::where('age <= ?', [-1])->where()->count(),
            'Less or equal'
        );
    }

    /**
     * @test
     */
    public function testIncrementAndDecrement()
    {
        $wall = \Model\WallTotal::incrementSummaryCounts($this->user->getId());
        $oldValue = $wall['total'];
        $wall = \Model\WallTotal::incrementSummaryCounts($this->user->getId());
        parent::assertEquals($oldValue + 1, $wall['total']);
        $wall->incrTotal(1);
        parent::assertEquals($oldValue + 2, $wall['total']);
        $wall->incrTotal(2);
        parent::assertEquals($oldValue + 4, $wall['total']);
        $wall->save();
        $wall = \Model\WallTotal::incrementSummaryCounts($this->user->getId(), 'DOWN');
        parent::assertEquals($oldValue + 3, $wall['total']);
    }

    /**
     * @test
     * @covers Model\User::load()
     * @covers User::whereFieldEq()
     * @covers User::any()
     * @covers Zer0\Model\Iterator::offsetSet()
     */
    public function testOffsetSet()
    {
        $users = User::whereFieldEq('id', [$this->user->getId()])->load();
        $user = User::any()->load(1)->first();
        $this->assertTrue((bool)$user);
        $users[$user->getId()] = $user;
        parent::assertEquals(2, $users->count());
        foreach ($users as $user) {
            $this->assertInstanceOf('Model\User', $users[$user->getId()]);
        }
    }

    /**
     * @test
     * @covers User::load()
     * @covers User::whereFieldEq()
     */
    public function testLoad()
    {
        $testUser = User::whereFieldEq('id', $this->user->getId())->load();
        parent::assertEquals($this->user->username, $testUser->username, 'Username mismatch');
        parent::assertEquals($this->user->name, $testUser->name, 'Name mismatch');
        parent::assertEquals($this->user->email, $testUser->email, 'Email mismatch');
        parent::assertEquals($this->user->sex, $testUser->sex, 'Sex mismatch');
        parent::assertEquals($this->user->password_hash, $testUser->password_hash, 'password_hash mismatch');
        parent::assertEquals($this->user->activated, $testUser->activated, 'Activated mismatch');
        parent::assertEquals($this->user->main_thumb, $testUser->main_thumb, 'Main_thumb mismatch');
        parent::assertEquals($this->user->birth, $testUser->birth, 'birth mismatch');
    }

    /**
     * @test
     * @covers User::save()
     * @covers User::load()
     * @covers User::whereFieldEq()
     */
    public function testUpdate()
    {
        $newName = Str::base64UrlEncode(random_bytes(5));
        $newAge = mt_rand(41, 80);
        $this->user->age = $newAge;
        $this->user->name = $newName;
        $this->user->save();
        $user = User::whereFieldEq('id', $this->user->getId())->load();
        parent::assertEquals($newName, $user->name, 'Checking if name was updated');
    }

    /**
     * @test
     * @covers User::where()
     * @covers User::load()
     * @covers User::whereFieldEq()
     * @covers User::count()
     * @covers User::first()
     */
    public function testWhere()
    {
        $id = $this->user->getId();
        $email = $this->user->email;

        $count = User::whereFieldEq('id', $id)->count();
        parent::assertEquals($count, 1);

        $user = User::whereFieldEq('id', $id)->load();
        parent::assertEquals($this->user->username, $user->username);

        $user = User::whereFieldEq('id', $id)->first();
        parent::assertEquals($this->user->username, $user->username);

        $user = User::where('email = :email', ["email" => $email])->first();
        parent::assertEquals($this->user->username, $user->username);

        $user = User::where('email = ?', [$email])->first();
        parent::assertEquals($this->user->username, $user->username);

        $user = User::where(
            'email = :email or username = :email',
            ['email' => $email]
        )->first();
        parent::assertEquals($this->user->username, $user->username);

        $user = User::where(
            'email = :0 or username = :0',
            [$email]
        )->first();
        parent::assertEquals($this->user->username, $user->username);

        $email2 = Str::random();
        $count = User::where('email IN (?)', [$email, $email2])->count();
        parent::assertEquals(1, $count);

        $count = User::where('email IN (?)', [])->count();
        parent::assertEquals(0, $count);
    }

    /**
     * @test
     * @covers User::get*()
     * @covers User::getId()
     */
    public function testGetters()
    {
        parent::assertEquals($this->user->username, $this->user['username']);
        parent::assertEquals($this->user->username, $this->user->getUsername());
        parent::assertEquals($this->user->getId(), $this->user['id']);
    }

    /**
     * @test
     * @covers User::verifyPassword()
     */
    public function testPasswordVerification()
    {
        $newPassword = Str::random();
        $this->user->password = $newPassword;
        $this->assertTrue($this->user->verifyPassword($newPassword), "Validation failed");
        $this->assertFalse($this->user->verifyPassword('Wrong'), "Validated wrong password!");
    }

    /**
     * @test
     * @covers User::getById()
     */
    public function testGetById()
    {
        $user = User::whereFieldEq('id', [$this->user->getId()])->load()->current();
        parent::assertEquals($this->user->username, $user['username'], 'Username mismatch');


        $user = User::whereFieldEq('id', [50 => $this->user->getId()])->load()->current();
        parent::assertEquals($this->user->username, $user['username'], 'Username mismatch');
    }

    /**
     * @expectedException \Zer0\Model\Exceptions\BundleException
     */
    public function testBlindUpdate()
    {
        $user = \Model\User::where("username=?", [$this->user->username])->bundleExceptions();
        $user->setAge(20)->save();
        $this->assertEmpty($user->validationErrors());

        $user->setAge("not as numeric");

        $this->assertArrayHasKey('age', $user->validationErrors());
        $user->save();
    }
}
