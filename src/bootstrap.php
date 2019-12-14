<?php
use \Zer0\App;
use \Dotenv\Exception\InvalidPathException;
use Zer0\Helpers\ErrorsAreExceptions;

if (!defined('ZERO_ROOT')) {
    define('ZERO_ROOT', getcwd());
}

require ZERO_ROOT . '/vendor/autoload.php';

ErrorsAreExceptions::makeItSo();

try {
    $_ENV = array_merge($_ENV, Dotenv\Dotenv::create(ZERO_ROOT, '.env')->load());
} catch (InvalidPathException $e) {}
try {
    $_ENV = array_merge($_ENV, Dotenv\Dotenv::create(ZERO_ROOT, '.env.build')->load());
} catch (InvalidPathException $e) {}

$app = new App($_ENV['ENV'] ?? 'dev', [
    ZERO_ROOT . '/conf',
    ZERO_ROOT . '/usr/conf',
]);
$app->buildTimestamp = $_ENV['BUILD_TS'] ?? null;
