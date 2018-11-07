<?php

namespace Zer0;

use Zer0\AppTraits\Brokers;
use Zer0\Config\Config;
use Zer0\Config\Tracy\BarPanel;
use Zer0\Traits\EventHandlers;

/**
 * Class App
 * @package Zer0
 */
class App
{
    use Brokers;
    use EventHandlers;

    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var string
     */
    public $env;

    /**
     * @var int
     */
    public $buildTimestamp;

    /**
     * @var Config
     */
    public $config;

    /**
     * App constructor.
     * @param string $env
     * @param array $confDir
     */
    public function __construct(string $env, array $confDir)
    {
        static::$instance = $this;
        $this->env = $env;
        $this->config = new Config($env, $confDir, $this);
        $tracy = $this->broker('Tracy')->get();
        if ($tracy !== null) {
            $tracy->addPanel(new BarPanel($this->config));
        }
    }

    /**
     * @return self
     */
    public static function instance(): self
    {
        return static::$instance;
    }

    /**
     * @param string $message
     * @param array $data
     * @return void
     */
    public function log(string $message, array $data = []): void
    {
    }
}
