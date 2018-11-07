<?php

use Zer0\HTTP\Exceptions\HttpError;

if (!defined('ZERO_ROOT')) {
    define('ZERO_ROOT', $_SERVER['ZERO_ROOT']);
}

require ZERO_ROOT . '/libraries/Zer0/src/bootstrap.php';
$app->broker('Autorun')->get();
/**
 * @var \Zer0\HTTP\HTTP $http
 */
$http = $app->broker('HTTP')->get();

try {
    $http->prepareEnv();
    if (!isset($_SERVER['ROUTE_CONTROLLER'])) {
        $http->routeRequest();
    }
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
