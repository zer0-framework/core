<?php
declare(strict_types=1);

namespace Zer0\Queue;

/**
 * Class SomeTask
 * @package Zer0\Queue
 */
final class SomeTask extends \Zer0\Queue\TaskAbstract
{
    /**
     * @var string
     */
    public $foo;

    /**
     * @var string
     */
    public $str;

    /**
     * SomeTask constructor.
     * @param string $str
     */
    public function __construct(string $str = '') {
        $this->str = $str;
    }

    /**
     *
     */
    public function execute(): void
    {
        $this->foo = 'bar';
        $this->str = strrev($this->str);
        $this->log('some log message');
        $this->complete();
    }
}
