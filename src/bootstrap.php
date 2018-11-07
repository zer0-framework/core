<?php
if (!defined('ZERO_ROOT')) {
    define('ZERO_ROOT', getcwd());
} else {
    chdir(ZERO_ROOT);
}

require ZERO_ROOT . '/vendor/autoload.php';

$app = new \Zer0\App(is_file(ZERO_ROOT . '/.env') ? rtrim(file_get_contents(ZERO_ROOT . '/.env')) : 'dev', [
    ZERO_ROOT . '/conf',
    ZERO_ROOT . '/usr/conf',
]);
if (is_file(ZERO_ROOT . '/.build-timestamp')) {
    $app->buildTimestamp = (int)file_get_contents(ZERO_ROOT . '/.build-timestamp');
}
