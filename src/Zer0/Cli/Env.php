<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;

/**
 * Class Build
 * @package Zer0\Cli\Controllers
 */
final class Env extends AbstractController
{
    /**
     * @var string
     */
    protected $command = 'env';

    /**
     * @param string|null $name
     * @param string|null $value
     * @param string $file
     */
    public function setAction(string $name = null, string $value = null, string $file = '.env'): void
    {
        $path = ZERO_ROOT . '/' . $file;
        $env = \Dotenv\Dotenv::create(dirname($path), basename($path))->load();

        $newLine = $name . '=' . escapeshellarg($value);
        if (!isset($env[$name])) {
            file_put_contents($path, $newLine . PHP_EOL, FILE_APPEND);
        } else {
            $lines = explode(PHP_EOL, file_get_contents($path));
            foreach ($lines as &$line) {
                $split = explode('=', trim($line));
                if ($split[0] === $name) {
                    $line = $newLine;
                }
            }
            file_put_contents($path, join(PHP_EOL, $lines));
        }
    }

    /**
     * @param string|null $name
     */
    public function unsetAction(string $name = null, string $file = '.env')
    {
        $path = ZERO_ROOT . '/' . $file;
        $env = \Dotenv\Dotenv::create(dirname($path), basename($path))->load();
        if (isset($env[$name])) {
            $lines = explode(PHP_EOL, file_get_contents($path));
            foreach ($lines as $k => $line) {
                $split = explode('=', trim($line));
                if ($split[0] === $name) {
                    unset($lines[$k]);
                }
            }
            file_put_contents($path, join(PHP_EOL, $lines));
        }
    }

    /**
     * @param string $file
     */
    public function listAction(string $file = '.env')
    {
        $path = ZERO_ROOT . '/' . $file;
        $env = \Dotenv\Dotenv::create(dirname($path), basename($path))->load();
        foreach ($env as $key => $value) {
            echo $key . '=' . escapeshellarg($value) . PHP_EOL;
        }
    }
}
