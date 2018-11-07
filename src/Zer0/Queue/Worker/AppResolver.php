<?php

namespace Zer0\Queue\Worker;

return new class extends \PHPDaemon\Core\AppResolver {
    public function __construct()
    {
        if (!defined('ZERO_ROOT')) {
            define('ZERO_ROOT', realpath(__DIR__ . '/../../../../..'));
        }
        require ZERO_ROOT . '/vendor/autoload.php';
    }
};
