<?php

namespace Zer0\Queue;

use Zer0\Exceptions\BaseException;
use Zer0\Exceptions\InvalidStateException;
use Zer0\Queue\Exceptions\RuntimeException;

/**
 * Class TaskAbstract
 * @package Zer0\Queue
 */
abstract class TaskAbstract
{
    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var string
     */
    private $_id;

    /**
     * @var string
     */
    private $_channel;

    /**
     * @var bool
     */
    private $invoked = false;

    /**
     * @var RuntimeException
     */
    private $exception;

    /**
     *
     */
    protected function before(): void
    {
    }

    /**
     * @throws RuntimeException
     * @throws \Throwable
     */
    abstract protected function execute(): void;

    /**
     *
     */
    protected function after(): void
    {
    }


    /**
     *
     */
    protected function onException(): void
    {
        // Subject to overload
    }

    /**
     * @return int
     */
    public function getTimeoutSeconds(): int
    {
        return 0;
    }


    /**
     * @return null|string ?string
     */
    final public function getId(): ?string
    {
        return $this->_id;
    }

    /**
     * @return null|string ?string
     */
    final public function getChannel(): ?string
    {
        return $this->_channel ?? 'default';
    }

    /**
     * @param $id
     */
    final public function setId(string $id): void
    {
        $this->_id = $id;
    }

    /**
     * @param string $channel
     */
    final public function setChannel(string $channel): void
    {
        $this->_channel = $channel;
    }

    /**
     * @param callable $callback
     * @return self
     */
    final public function setCallback(callable $callback): TaskAbstract
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @throws InvalidStateException
     */
    final public function __invoke()
    {
        if ($this->invoked) {
            throw new InvalidStateException('The task instance has already been invoked.');
        }
        $this->invoked = true;
        try {
            $this->before();
            $this->execute();
        } catch (BaseException $exception) {
            $this->exception($exception);
        } catch (\Throwable $exception) {
            $this->exception(new RuntimeException('Uncaught exception ' . $exception));
        }
    }

    /**
     *
     */
    final protected function complete(): void
    {
        $callback = $this->callback;
        $this->callback = null;
        $this->after();
        call_user_func($callback, $this);
    }

    /**
     * @param BaseException $exception
     */
    final protected function exception(BaseException $exception): void
    {
        $callback = $this->callback;
        $this->callback = null;
        $this->exception = $exception;
        $this->onException();
        $this->after();
        call_user_func($callback, $this);
    }

    /**
     * @return null|\Throwable
     */
    final public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    /**
     * @return bool
     */
    final public function hasException(): bool
    {
        return $this->exception !== null;
    }

    /**
     * @return TaskAbstract
     * @throws BaseException
     */
    final public function throwException(): self
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this;
    }
}
