<?php

namespace Zer0\Model;

use Model\FriendRequest;
use Zer0\TestCase;

/**
 * Class ValidationTest
 * @package Zer0\Model
 */
class ValidationTest extends TestCase
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
     * @covers \Zer0\Model\Validator::validate()
     * @covers \Zer0\Model\Validator::validationErrors()
     */
    public function testRequiredValidation()
    {
        $req = FriendRequest::create()->bundleExceptions()->attr([
            'id' => 12,
            'requested' => 2332,
            'sent' => date('Y-m-d H:i:s'),
            'msg' => 'some string',
            'ignored' => false,
        ]);
        $this->assertFalse($req->validate());
        $this->assertArrayHasKey('requester', $req->validationErrors());
    }

    /**
     * @test
     * @covers \Zer0\Model\Validator::validate()
     * @covers \Zer0\Model\Validator::validationErrors()
     */
    public function testNumericValidation()
    {
        $req = FriendRequest::create()->bundleExceptions()->attr([
            'id' => 12,
            'requested' => 2332,
            'requester' => 'string',
            'sent' => date('Y-m-d H:i:s'),
            'msg' => 'some string',
            'ignored' => false,
        ]);
        $this->assertFalse($req->validate());
        parent::assertEquals(
            [
                'requester' => [
                    'type' => null,
                    'msg' => 'requester: does not meet a rule \'required\': NULL',
                    'field' => 'requester',
                    'value' => null,
                    'requirement' => 'required',
                ]
            ],
            $req->validationErrors()
        );
    }

    /**
     * @test
     * @covers \Zer0\Model\Validator::validate()
     * @covers \Zer0\Model\Validator::validationErrors()
     */
    public function testCorrectValidation()
    {
        $req = FriendRequest::create([
            'id' => 12,
            'requested' => 2332,
            'requester' => 221,
            'sent' => date('Y-m-d H:i:s'),
            'msg' => 'some string',
            'ignored' => false,
        ]);
        $req->validate();
        $this->assertTrue(count($req->validationErrors()) === 0);
    }

    /**
     * @test
     * @covers \Zer0\Model\Validator::validate()
     * @covers \Zer0\Model\Validator::validationErrors()
     */
    public function testMultipleErrors()
    {
        $req = FriendRequest::create()->bundleExceptions()->attr([
            'id' => 12,
            'requested' => 2332,
            'requester' => 221,
            'sent' => "ads",
            'msg' => 'some string',
            'ignored' => 41,
        ]);
        $this->assertFalse($req->validate());
        parent::assertEquals(
            [
                'sent' => [
                    'type' => null,
                    'msg' => 'sent: does not meet a rule \'date\': \'ads\'',
                    'field' => 'sent',
                    'value' => 'ads',
                    'requirement' => 'date',
                ],
                'ignored' => [
                    'type' => null,
                    'msg' => 'ignored: does not meet a rule \'boolean\': 41',
                    'field' => 'ignored',
                    'value' => 41,
                    'requirement' => 'boolean',
                ],
            ],
            $req->validationErrors()
        );
    }

    /**
     * @expectedException \Zer0\Model\Exceptions\BundleException
     */
    public function testBlindUpdate()
    {
        $user = \Model\User::whereFieldEq('username', $this->user->username)
            ->bundleExceptions();
        $user->setAge(20)->save();
        $this->assertEmpty($user->validationErrors());

        $user->setAge('not integer');
        $user->validate();

        parent::assertEquals(
            $user->validationErrors(),
            [
                'age' => [
                    'type' => null,
                    'msg' => 'age: does not meet a rule \'integer\': \'not integer\'',
                    'field' => 'age',
                    'value' => 'not integer',
                    'requirement' => 'integer',
                ]
            ]
        );
        $user->save();
    }

    /**
     * @expectedException \Zer0\Model\Exceptions\InvalidValueException
     * @expectedExceptionMessage does not meet a rule 'integer': 'not as integer'
     */
    public function testSingleModeUpdate()
    {
        $user = new \Model\User();
        $user->setAge('not as integer');
    }

    /**
     * @test
     */
    public function testUnsetValidation()
    {
        $user = \Model\User::whereFieldEq('id', $this->user->getId());
        $this->setExpectedException(
            'Core\\Model\\Exceptions\\InvalidValue',
            "username: does not meet a rule 'required': NULL"
        );
        $user->unsetUsername();
    }
}
