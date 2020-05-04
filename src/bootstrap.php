<?php
use \Zer0\App;
use \Dotenv\Exception\InvalidPathException;
use Zer0\Helpers\ErrorsAreExceptions;

if (!defined('ZERO_ROOT')) {
    define('ZERO_ROOT', getcwd());
}

require ZERO_ROOT . '/vendor/autoload.php';

ErrorsAreExceptions::makeItSo();

foreach (['.env', '.env.build'] as $filename) {
    try {
        $env = Dotenv\Dotenv::create(ZERO_ROOT, $filename)->load();
        $_ENV = array_merge($_ENV, $env);
        $_SERVER = array_merge($_SERVER, $env);
    } catch (InvalidPathException $e) {}
}

$app = new App($_ENV['ENV'] ?? 'dev', [
    ZERO_ROOT . '/conf',
    ZERO_ROOT . '/usr/conf',
]);
$app->buildTimestamp = $_ENV['BUILD_TS'] ?? null;
