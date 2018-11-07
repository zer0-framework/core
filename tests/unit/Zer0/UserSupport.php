<?php

namespace Tests;

/**
 * Trait UserSupport
 * @package Tests
 */
trait UserSupport
{
    /**
     * @var int Number of users to create
     */
    protected $usersNum = 1;
    /**
     * @var \Model\User
     */
    protected $user = null;
    /**
     * @var \Model\User[]
     */
    protected $users = [];
    /**
     * @var string - valid password for user
     */
    protected $password = 'R40ttyKCbQiIFVzM';

    /**
     * @return \Model\User
     * @throws \Exception
     */
    public function createNewUser()
    {
        $user = \Model\User::create([
            'username' => 'Username',
            'name' => 'Name',
            'sex' => 'male',
            'password' => $this->password,
            'birth' => '10-09-1984',
            'email' => 'foo@bar.tld',
            'activated' => '1',
            'main_thumb' => null,
        ]);

        return $user;
    }

    /**
     * Create a mockup user model and insert it into db
     * for testing purposes.
     *
     * @return void
     * @throws \Exception
     */
    public function setUp()
    {
        if (method_exists('parent', 'setUp')) {
            parent::setUp();
        }
        for ($i = 0; $i < $this->usersNum; $i++) {
            $this->users[] = $this->createNewUser();
        }
        $this->user = current($this->users);
    }
}
