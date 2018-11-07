<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;
use Zer0\HTTP\Router\JSGenerator;
use Zer0\HTTP\Router\NginxGenerator;

/**
 * Class Build
 * @package Zer0\Cli\Controllers
 */
final class Build extends AbstractController
{
    /**
     * @var \Zer0\Queue\Pools\Base
     */
    protected $queue;

    /**
     * @var string
     */
    protected $command = 'build';

    /**
     * @param mixed ...$args
     * @throws \Exception
     */
    public function nginxAction(...$args): void
    {
        $indentStr = function ($str, $n) {
            return preg_replace('~^~m', str_repeat("\t", $n), $str);
        };

        $config = $this->app->broker('HTTP')->getConfig();

        $destfile = $config->nginx_folder . '/routes.conf';

        $routesGenerator = new NginxGenerator($config->Routes->toArray());

        $destfile = $config->nginx_folder . '/server.conf';
        ob_start();
        echo "#### The file has been generated automatically\n"
            . "#### Date: " . date('r') . "\n"
            . "#### DO NOT MODIFY THIS FILE MANUALLY, YOUR CHANGES WILL BE OVERWRITTEN!\n\n";
        include $config->nginx_folder . '/server.conf.php';
        $body = ob_get_contents();
        ob_end_clean();

        // Writing into the file
        file_put_contents($tmp = tempnam(dirname($destfile), 'cfg'), $body);
        rename($tmp, $destfile);
        chmod($destfile, 0755);
        $this->cli->successLine("Written to $destfile in " . $this->elapsedMill() . " ms.");
    }

    public function routesjsAction(): void
    {
        $destfile = ZERO_ROOT . '/public/js/Routes.cfg.js';

        $routes = $this->app->broker('HTTP')->getConfig()->Routes;
        $generator = new JSGenerator($routes->toArray());
        $cfg = $generator->generate();

        // Writing into the file
        file_put_contents($tmp = tempnam(dirname($destfile), 'cfg'), $cfg);
        rename($tmp, $destfile);
        chmod($destfile, 0755);
        $this->cli->successLine("Written to $destfile in " . $this->elapsedMill() . " ms.");
    }

    /**
     *
     */
    public function allAction()
    {
        foreach ($this->getActions() as $action) {
            if ($action === 'all') {
                continue;
            }
            $this->cli->handleCommand('\\' . static::class, $action);
        }
    }

    /**
     *
     */
    public function listIncludedFiles(): void
    {
        foreach (get_included_files() as $file) {
            echo substr($file, strlen(getcwd())) . "\n";
        }
    }
}
