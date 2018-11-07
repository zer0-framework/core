<?php

namespace Zer0\FileStorage;

use easmith\selectel\storage\SelectelStorage;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\FileStorage\Exceptions\FileNotFoundException;
use Zer0\FileStorage\Exceptions\OperationFailedException;

/**
 * Class Selectel
 * @package Zer0\FileStorage
 */
final class Selectel extends Base
{
    /**
     * @var ConfigInterface
     */
    public $config;

    /**
     * @var SelectelStorage
     */
    protected $client;

    /**
     * @var SelectelStorage
     */
    protected $container;

    /**
     * @var bool
     */
    protected $initialized = false;

    protected function init()
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        require_once ZERO_ROOT . '/cli/vendor/autoload.php';
        $this->client = new SelectelStorage($this->config->auth_user, $this->config->auth_key);
    }

    /**
     * @param string $source
     * @param string $container
     * @param string $path
     * @param array $options
     * @return void
     * @throws FileNotFoundException
     * @throws OperationFailedException
     */
    public function putFile(string $source, string $container, string $path, array $headers = []): void
    {
        try {
            $this->init();
            $container = $this->client->getContainer($this->resolveContainer($container));
            $headers['ETag'] = md5_file($source);
            $container->putFile($source, $path, $headers);
        } catch (\Throwable $e) {
            throw new OperationFailedException('putFile operation failed', 0, $e);
        }
    }

    /**
     * @param string $source
     * @param string $container
     * @param string $path
     * @param array $options
     * @return void
     * @throws FileNotFoundException
     * @throws OperationFailedException
     */
    public function putArchive(string $source, string $container, string $path, array $headers = []): void
    {
        if (!preg_match('~\.((?:tar(?:\.gz)?|gzip))$~i', $source, $match)) {
            throw new OperationFailedException('Unsupported archive type. Supported types: .tar, .tar.gz, .gzip');
        }

        try {
            $this->init();
            $container = $this->client->getContainer($this->resolveContainer($container));
            $headers['ETag'] = md5_file($source);
            $container->putFile($source, $path, $headers, ['extract-archive' => $match[1]]);
        } catch (\Throwable $e) {
            throw new OperationFailedException('putFile operation failed', 0, $e);
        }
    }

    /**
     * @return array
     * @throws OperationFailedException
     */
    public function listContainers(): array
    {
        try {
            $this->init();
            return $this->client->listContainers();
        } catch (\Throwable $e) {
            throw new OperationFailedException('listContainers operation failed', 0, $e);
        }
    }

    public function listFiles(string $container, string $prefix, string $delimiter = null): array
    {
        try {
            $this->init();
            $container = $this->client->getContainer($this->resolveContainer($container));
            return $container->listFiles(10000, null, $prefix, null, $delimiter);
        } catch (\Throwable $e) {
            throw new OperationFailedException('listFiles operation failed', 0, $e);
        }
    }

    /**
     * @param string $container
     * @param string $path
     * @return bool
     * @throws OperationFailedException
     */
    public function delete(string $container, string $path): void
    {
        try {
            $this->init();
            $container = $this->client->getContainer($this->resolveContainer($container));
            $container->delete($path);
        } catch (\Throwable $e) {
            throw new OperationFailedException('delete operation failed', 0, $e);
        }
    }
}
