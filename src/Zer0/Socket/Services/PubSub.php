<?php

namespace Zer0\Socket\Services;

use Zer0\PubSub\Pools\BaseAsync;
use Zer0\Socket\Socket;

/**
 * Class PubSub
 * @package Zer0\Socket\Services
 */
class PubSub extends Generic
{
    /**
     * @var BaseAsync
     */
    protected $pubsub;

    /**
     * Constructor
     */
    protected function init()
    {
        $this->pubsub = $this->socket->app->broker('PubSubAsync')->get();
        $this->socket->on('finish', function () {
            $this->pubsub->unsubscribeAll();
        });
    }

    /**
     * Subscribe on channels
     *
     * @param array $channels Array of channels to subscribe
     * @param callable $cb
     * @callback $cb (array $event)
     * @return void
     */
    public function subscribe($channels, $cb)
    {
        if (!Socket::ensureCallback($cb)) {
            return;
        }
        $channels = (array) $channels;
        foreach ($channels as $k => $chan) {
            $chan = trim($chan);
            $colonSplit = explode(':', $chan);
            $dotSplit = explode('.', $colonSplit[0]);
            if ($dotSplit[0] === 'user') {
                if (!$this->socket->user || ($dotSplit[1] ?? null) !== (string)$this->socket->user->id) {
                    unset($channels[$k]);
                }
            }
        }
        if (!$channels) {
            return;
        }
        $this->pubsub->subscribe($channels, $cb);
    }

    /**
     * Unsubscribe from channels
     *
     * @param array $channels Array of channels
     * @return void
     */
    public function unsubscribe($channels)
    {
        $this->pubsub->unsubscribe($channels);
    }
}
