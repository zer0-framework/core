<?php

namespace Zer0\Session\Storages;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class BaseAsync
 * @package Zer0\Session\Storages
 */
abstract class BaseAsync
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
     * @param callable $cb = null
     */
    abstract public function read(string $id, $cb = null): void;

    /**
     * @param string $id
     * @param array $data
     * @param callable $cb = null
     * @return void
     */
    abstract public function write(string $id, array $data, $cb = null): void;

    /**
     * @param string $id
     * @param array $transaction
     * @param callable $cb = null
     * @return void
     */
    abstract public function transaction(string $id, array $transaction, $cb = null);

    /**
     * @param string $id
     * @param callable $cb = null
     * @return void
     */
    abstract public function destroy(string $id, $cb = null);

    /**
     * @param string $old
     * @param string $new
     * @param callable $cb = null
     * @return void
     */
    abstract public function rename(string $old, string $new, $cb = null): void;
}
