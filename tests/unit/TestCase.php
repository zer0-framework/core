<?php
declare(strict_types=1);

namespace Zer0;

/**
 * Class TestCase
 * @package Zer0
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var App
     */
    protected $app;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $this->app = $GLOBALS['app'];
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown()
    {
    }
}
