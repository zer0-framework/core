<?php

namespace Zer0\AppTraits;

use Zer0\Brokers\Base;

/**
 * Trait Brokers
 * @package Zer0\AppTraits
 */
trait Brokers
{
    /**
     * @var array
     */
    protected $brokers = [];

    /**
     * @alias broker()
     * @param string $nameOrClass
     * @return Base
     */
    public function __invoke(string $nameOrClass): Base
    {
        return $this->broker($nameOrClass);
    }

    /**
     * @param string $nameOrClass
     * @param string $instance
     * @param bool $caching
     * @return mixed
     */
    public function factory(string $nameOrClass, string $instance = '', bool $caching = true)
    {
        return $this->broker($nameOrClass)->get($instance, $caching);
    }

    /**
     * @param string $nameOrClass
     * @return Base
     */
    public function broker(string $nameOrClass): Base
    {
        $broker = $this->brokers[$nameOrClass] ?? null;
        if ($broker !== null) {
            return $broker;
        }

        try {
            if (strpos($nameOrClass, '\\') === false) {
                $class = $this->config->Brokers->getValue($nameOrClass);
            } else {
                $class = $nameOrClass;
            }
            if ($class === null) {
                $split = explode('\\', \Zer0\Brokers\Base::class);
                $split[count($split) - 1] = $nameOrClass;
                $class = implode('\\', $split);
            }
            return $this->brokers[$nameOrClass] = $broker = new $class($this);
        } finally {
            if (isset($broker)) {
                $broker->setNameIfEmpty($nameOrClass);
            }
        }
    }
}
