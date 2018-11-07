<?php
declare(strict_types=1);

namespace Zer0\Session;

use Zer0\TestCase;

/**
 * Class SessionTest
 * @package Zer0\Session
 */
final class SessionTest extends TestCase
{

    /**
     * @var Session
     */
    protected $session;

    /**
     * This method is called before each test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->session = $this->app->broker('Session')->get();
    }

    public function testSimple(): void
    {
        $this->session->start();

        $_SESSION['foo'] = 'bar';
        $this->assertEquals('bar', $_SESSION['foo']);

        $_SESSION->incr('counter', 5);
        $this->assertEquals(5, $_SESSION['counter']);
    }
}
