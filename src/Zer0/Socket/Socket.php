<?php

namespace Zer0\Socket;

use PHPDaemon\Core\ClassFinder;
use PHPDaemon\DNode\DNode;
use PHPDaemon\Traits\EventHandlers;
use Zer0\App;
use Zer0\Model\Exceptions\Interfaces\Gatherable;
use Zer0\Session\SessionAsync;

/**
 * This class represents a living client-server SockJS connection
 * @package Zer0\Socket
 */
class Socket extends \PHPDaemon\WebSocket\Route
{
    // Core traits
    use DNode;
    use EventHandlers;

    /**
     * @var array
     */
    protected $services = [];


    /**
     * @var SessionAsync
     */
    public $session;

    /**
     *
     * @var User
     */
    public $user;

    /**
     * @var App
     */
    public $app;

    /**
     * Called when the connection is handshaked.
     * @return void
     */
    public function onHandshake()
    {
        $this->initServices();
        $this->defineLocalMethods();
    }

    /**
     *
     */
    protected function initServices()
    {
        $ns = ClassFinder::getNamespace(\Zer0\Socket\Services\Generic::class);
        foreach ($this->appInstance->services as $serviceName) {
            $class = ClassFinder::find($serviceName, $ns, '~');
            $this->services[$serviceName] = new $class($this);
        }
    }

    /**
     * Get a service by name
     * @param $service
     * @return \Zer0\Socket\Services\Generic|null
     */
    public function service($service)
    {
        return isset($this->services[$service]) ? $this->services[$service] : null;
    }

    /**
     * Subscribe on channels
     *
     * @param Callback $cb
     * @return void
     */
    protected function servicesMethod($cb)
    {
        if (!static::ensureCallback($cb)) {
            return;
        }
        $services = [];

        foreach ($this->services as $name => $service) {
            $methods = [];
            foreach (get_class_methods($service) as $method) {
                if ($method[0] === '_') {
                    continue;
                }
                $methods[$method] = [$service, $method];
            }
            $services[$name] = $methods;
        }
        $this->persistentMode = true;
        $cb($services);
        $this->persistentMode = false;
    }

    /**
     * Called when session finished.
     * @return void
     */
    public function onFinish()
    {
        foreach ($this->services as $service) {
            $service->finish();
        }
        $this->services = null;
        $this->cleanupEventHandlers();
        $this->cleanup();
        parent::onFinish();
    }

    /**
     * Uncaught exception handler
     * @param $e
     * @return boolean Handled?
     */
    public function handleException($e)
    {
        if ($this->app !== 'prod') {
            D('exception — ' . $e);
        }
        if ($e instanceof Gatherable) {
            $this->callRemote('exception', $e->getInfo());
        } else {
            $this->callRemote('exception', get_class($e));
        }
        return true;
    }
}
