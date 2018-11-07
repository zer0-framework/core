<?php

namespace Zer0\Socket;

use PHPDaemon\Servers\WebSocket\Pool as WebSocketPool;
use PHPDaemon\SockJS\Application as SockJS;
use Zer0\App;
use Zer0\HTTP\Exceptions\RouteNotFound;

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
     * @var WebSocketPool
     */
    protected $ws;

    /**
     * @var SockJS
     */
    protected $sockjs;

    /**
     * @var \PHPDaemon\Clients\Redis\Pool
     */
    public $redis;

    public $services;

    /**
     * @return array|bool
     */
    protected function getConfigDefaults()
    {
        return [
            'redis-prefix' => ''
        ];
    }

    /**
     * Called when the worker is ready to go.
     * @return void
     * @throws RouteNotFound
     */
    public function onReady()
    {
        $app = null;
        require ZERO_ROOT . '/libraries/Zer0/src/bootstrap.php';

        define('ZERO_ASYNC', 1);

        $this->app = $app;

        $routes = $app->broker('HTTP')->getConfig()->Routes;

        $config = $app->broker('Socket')->get()->config;

        $route = $routes->{$config->route} ?? null;

        if (!$route) {
            throw new RouteNotFound("Route '" . $config->route . "' not found.");
        }

        $this->services = $config->services;

        $this->redis = $app->broker('RedisAsync')->get();

        $this->ws = WebSocketPool::getInstance();

        $class = $config->socketClass;
        $this->ws->addRoute(trim($route['path'], '/'), function ($client) use ($class) {
            return new $class($client, $this);
        });

        $this->sockjs = SockJS::getInstance('');
        $this->sockjs->setRedis($this->redis);

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
        return $this->sockjs->handleRequest($parent, $upstream);
    }
}
