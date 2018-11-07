<?php

namespace Zer0\FastCGI;

use Zer0\App;

/**
 * Class Application
 * @package InterpalsD
 */
class Application extends \PHPDaemon\Core\AppInstance
{
    /**
     * @var App
     */
    public $app;

    /**
     * Called when the worker is ready to go.
     * @return void
     */
    public function onReady()
    {
        $app = null;
        require ZERO_ROOT . '/libraries/Zer0/src/bootstrap.php';
        $this->app = $app;
    }

    /**
     * Handle the request
     * @param  object $parent Parent request
     * @param  object $upstream Upstream application
     * @return object Request
     */
    public function handleRequest($parent, $upstream)
    {
        return new Request($this, $upstream, $parent);
    }


    /**
     * Setting default config options
     * @return array|bool
     */
    protected function getConfigDefaults()
    {
        return [
            'redis-prefix' => '',
            'wss-name' => '',
            'sockjs-name' => '',
            'wss-route' => 'socket',
            'ipenv' => 'local',
        ];
    }
}
