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
     *
     */
    public function execute(): void
    {
        $this->foo = 'bar';
        $this->log('some log message');
        $this->complete();
    }
}
