<?php
require __DIR__ . '/bootstrap.php';
require 'cli/vendor/autoload.php';

if (!is_file(ZERO_ROOT . '/.env')) {
    fwrite(STDERR, '.env file cannot be found. Make sure to run the script from the project directory.' . PHP_EOL);
    exit(1);
}
