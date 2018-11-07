<?php

namespace Zer0\Socket;

return new class extends \PHPDaemon\Core\AppResolver {
    public function __construct()
    {
        if (!defined('ZERO_ROOT')) {
            define('ZERO_ROOT', realpath(__DIR__ . '/../../../../..'));
        }
        require ZERO_ROOT . '/vendor/autoload.php';
    }

    /**
     * @param object $req Request.
     * @param object $upstream AppInstance of Upstream.
     * @description Routes incoming request to related application. Method is for overloading.
     * @return string Application's name.
     */
    public function getRequestRoute($req, $upstream)
    {
        return '\\' . Application::class;
    }
};
