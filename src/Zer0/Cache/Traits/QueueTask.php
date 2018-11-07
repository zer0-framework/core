<?php

namespace Zer0\Cache\Traits;

use Zer0\App;
use Zer0\Cache\Item\Item;

/**
 * Trait QueueTask
 * @package Zer0\Cache\Traits
 */
trait QueueTask
{
    /**
     * @param App $app
     * @param int $timeout
     * @return \Closure
     */
    public static function cacheCallback(App $app, int $timeout = 3): \Closure
    {
        return function (Item $item) use ($app, $timeout) {
            try {
                $app->broker('Queue')->get()->enqueueWait(
                    new self,
                    $timeout
                )->throwException();
                $item->reset()->get();
            } catch (\Zer0\Queue\Exceptions\WaitTimeoutException $e) {
                // Задача не завершилась за 3 секунды
            }
        };
    }
}
