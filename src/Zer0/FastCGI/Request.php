<?php
namespace Zer0\FastCGI;

use Zer0\App;
use Zer0\HTTP\Exceptions\HttpError;

use PHPDaemon\Core\Daemon;
use PHPDaemon\HTTPRequest\Generic;

/**
 * Class Request
 * @package Zer0\FastCGI
 */
class Request extends Generic
{
    /**
     * Called when request iterated.
     * @return void Status.
     * @throws \Exception
     */
    public function run()
    {
        /**
         * @var App $app
         */
        $app = $this->appInstance->app;

        /**
         * @var \Zer0\HTTP\HTTP $http
         */
        $http = $app->broker('HTTP')->get();

        //$app->broker('Tracy')->get();

        try {
            if (!isset($_SERVER['ROUTE_CONTROLLER'])) {
                $http->routeRequest();
            }
            $http->prepareEnv();
            $http->trigger('beginRequest');
            $http->handleRequest(
                $_SERVER['ROUTE_CONTROLLER'] ?? '',
                $_SERVER['ROUTE_ACTION'] ?? ''
            );
        } catch (HttpError $error) {
            $http->handleHttpError($error);
        } catch (\Throwable $exception) {
            $http->handleException($exception);
        } finally {
            $http->handleRequestEnd();
        }
    }
}
