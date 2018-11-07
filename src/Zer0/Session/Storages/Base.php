<?php

namespace Zer0\Session\Storages;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Base
 * @package Zer0\Session\Storages
 */
abstract class Base
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * Base constructor.
     * @param App $app
     * @param ConfigInterface $config
     */
    public function __construct(App $app, ConfigInterface $config)
    {
        $this->app = $app;
        $this->config = $config;
        $this->init();
    }


    /**
     * @param string $id
     * @return array
     */
    abstract public function read(string $id): array;

    /**
     * @param string $id
     * @param array $data
     * @return void
     */
    abstract public function write(string $id, array $data): void;

    /**
     * @param string $id
     * @param array $transaction
     * @return mixed
     */
    abstract public function transaction(string $id, array $transaction): void;

    /**
     * @param string $id
     */
    abstract public function destroy(string $id): void;

    /**
     * @param string $old
     * @param string $new
     */
    abstract public function rename(string $old, string $new): void;
}
