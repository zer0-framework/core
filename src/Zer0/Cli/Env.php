<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;

/**
 * Class Build
 *
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
     * @param string      $file
     */
    public function setAction (string $name = null, string $value = null, string $file = '.env'): void
    {
        $path       = ZERO_ROOT . '/' . $file;
        $env        = self::loadEnv($path);
        $env[$name] = $value;
        self::dumpEnv($path, $env);
    }

    /**
     * @param string $path
     *
     * @return array
     */
    private static function loadEnv (string $path): array
    {
        return \Dotenv\Dotenv::create(dirname($path), basename($path))->load();
    }

    /**
     * @param string $path
     * @param array  $array
     */
    private static function dumpEnv (string $path, array $env): void
    {
        $contents = '';
        foreach ($env as $key => $value) {
            $contents .= $key . '=' . escapeshellarg($value) . PHP_EOL;
        }
        file_put_contents($path, $contents);
    }

    /**
     * @param string|null $name
     */
    public function unsetAction (string $name = null, string $file = '.env'): void
    {
        $path = ZERO_ROOT . '/' . $file;
        $path       = ZERO_ROOT . '/' . $file;
        $env        = self::loadEnv($path);
        unset($env[$name]);
        self::dumpEnv($path, $env);
    }

    /**
     * @param string $file
     */
    public function listAction (string $file = '.env'): void
    {
        $path = ZERO_ROOT . '/' . $file;
        $env  = \Dotenv\Dotenv::create(dirname($path), basename($path))->load();
        foreach ($env as $key => $value) {
            echo $key . '=' . escapeshellarg($value) . PHP_EOL;
        }
    }
}
