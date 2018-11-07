<?php

namespace Zer0\Cache\Pools;

use Zer0\App;
use Zer0\Cache\Item\Item;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Base
 * @package Zer0\Cache\Pools
 */
abstract class Base
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var App
     */
    protected $app;

    /**
     * Base constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * @param string $key
     * @return Item
     */
    public function item(string $key): Item
    {
        return new Item($key, $this);
    }

    /**
     * @param string $key
     * @param bool &$hasValue
     * @return mixed
     */
    abstract public function getValueByKey(string $key, &$hasValue = null);

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl Seconds to live
     * @return bool
     */
    abstract public function saveKey(string $key, $value, int $ttl = 0): bool;


    /**
     * @param Item $item
     * @return mixed
     */
    abstract public function invalidate(Item $item): bool;

    /**
     * @param string $key
     * @return mixed
     */
    abstract public function invalidateKey(string $key): bool;

    /**
     * @param Item $item
     */
    abstract public function save(Item $item);

    /**
     * @param string $tag
     * @return bool
     */
    abstract public function invalidateTag(string $tag);
}
