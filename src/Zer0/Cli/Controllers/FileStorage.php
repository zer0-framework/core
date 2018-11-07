<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;

/**
 * Class FileStorage
 * @package Zer0\Cli\Controllers
 */
final class FileStorage extends AbstractController
{
    /**
     * @var \Zer0\FileStorage\Base
     */
    protected $fileStorage;

    /**
     * @var string
     */
    protected $command = 'filestorage';

    /**
     *
     */
    public function before(): void
    {
        parent::before();
        $this->fileStorage = $this->app->broker('FileStorage')->get();
    }

    /**
     *
     */
    public function listContainersAction(): void
    {
        foreach ($this->fileStorage->listContainers() as $container) {
            $this->cli->writeln($container);
        }
        $this->cli->writeln('');
    }

    public function lsAction(string $container = '', string $prefix = '')
    {
        $split = explode('/', $container, 2);
        $container = $split[0];
        if (isset($split[1])) {
            $prefix = $split[1];
        }
        $delimiter = '/';

        if (substr($prefix, -2) === '//') {
            $delimiter = null;
            $prefix = substr($prefix, 0, -1);
        }

        foreach ($this->fileStorage->listFiles($container, $prefix, $delimiter) as $file) {
            $this->cli->writeln($file);
        }
        $this->cli->writeln('');
    }

    public function putFileAction(string $source, string $container = '', string $path = '')
    {
        if ($path === '' || substr($path, 0, -1) === '/') {
            $path .= basename($source);
        }
        $this->fileStorage->putFile($source, $container, $path);
    }

    public function putArchiveAction(string $source, string $container = '', string $path = '')
    {
        if ($path === '' || substr($path, 0, -1) === '/') {
            $path .= basename($source);
        }
        $this->fileStorage->putArchive($source, $container, $path);
    }
}
