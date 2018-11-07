<?php

namespace Zer0\PubSub;

/**
 * Class Message
 * @package Zer0\PubSub
 */
class Message implements MessageInterface
{
    /**
     * @var string
     */
    public $channel;

    /**
     * @var mixed
     */
    public $payload;

    /**
     * Message constructor.
     * @param string $channel
     * @param mixed $payload
     */
    public function __construct(string $channel, $payload)
    {
        $this->channel = $channel;
        $this->payload = $payload;
    }
}
