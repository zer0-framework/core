<?php

namespace Zer0\Brokers;

use PHPDaemon\Core\ClassFinder;
use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Base
 * @package Zer0\Brokers
 */
abstract class Base
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @var array
     */
    protected $instances = [];

    /**
     * @var bool
     */
    protected $caching = true;

    /**
     * @var string
     */
    protected $broker;

    /**
     * @var ?string
     */
    protected $lastName;

    /**
     * Redis constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->broker = $name;
    }


    /**
     * @param string $name
     */
    public function setNameIfEmpty(string $name): void
    {
        $this->broker = $this->broker ?? $name;
    }

    /**
     * @param string $name
     * @return null|ConfigInterface
     */
    public function getConfig(string $name = ''): ?ConfigInterface
    {
        $broker = $this->broker ?? ClassFinder::getClassBasename(get_class($this));
        $config = $this->app->config->{$broker};
        if (strlen($name)) {
            $config = $config->{$name};
        }
        return $config;
    }

    /**
     * @param ConfigInterface $config
     * @return mixed
     */
    /**
     * @param ConfigInterface $config
     * @return mixed
     */
    /**
     * @param ConfigInterface $config
     * @return mixed
     */
    abstract public function instantiate(ConfigInterface $config);

    /**
     * @param string $name
     * @param bool $caching = true
     */
    public function get(string $name = '', bool $caching = true)
    {
        if ($this->caching && $caching) {
            if (array_key_exists($name, $this->instances)) {
                return $this->instances[$name];
            }
        }

        $this->lastName = $name;
        $instance = $this->instantiate($this->getConfig($name));

        if ($this->caching) {
            $this->instances[$name] = $instance;
        }


        return $instance;
    }
}
