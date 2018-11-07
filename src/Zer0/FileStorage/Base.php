<?php

namespace Zer0\FileStorage;

use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\FileStorage\Exceptions\FileNotFoundException;
use Zer0\FileStorage\Exceptions\OperationFailedException;
use Zer0\Helpers\Str;

/**
 * Class Base
 * @package Zer0\FileStorage
 */
abstract class Base
{
    /**
     * @var ConfigInterface
     */
    public $config;

    /**
     *  Constructor.
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $source
     * @param string $container
     * @param string $path
     * @param array $options = []
     * @return void
     * @throws FileNotFoundException
     * @throws OperationFailedException
     */
    abstract public function putFile(string $source, string $container, string $path, array $options = []): void;

    /**
     * @param string $path
     * @param string $container
     * @return string
     */
    public function getUrl(string $container, string $path): string
    {
        $container = $this->resolveContainer($container);
        return $this->config->base_url . '/' . $container . '/' . $path;
    }

    /**
     * @param string $container
     * @param string $path
     * @return void
     */
    abstract public function delete(string $container, string $path): void;

    /**
     * @param string $name
     * @param string $container
     * @param string $prefix
     * @return string
     * @throws FileNotFoundException
     * @throws OperationFailedException
     */
    public function saveUploadedFile(string $name, string $container, string $prefix = ''): string
    {
        $container = $this->resolveContainer($container);
        $file = $_FILES[$name] ?? null;
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $path = $prefix
            . basename(Str::base64UrlEncode(sha1_file($file['tmp_name'], true)))
            . '/'
            . basename($file['name']);

        $this->putFile($file['tmp_name'], $container, $path, [
            'content-type' => $file['type'] ?? null,
        ]);

        return $path;
    }

    /**
     * @param string $container
     * @return string
     */
    public function resolveContainer(string $container): string
    {
        return $this->config->container_aliases[$container] ?? $container;
    }
}
