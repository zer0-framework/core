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
     * @param string $name
     * @return Base
     */
    public function __invoke(string $name): Base
    {
        return $this->broker($name);
    }

    /**
     * @param string $name
     * @return Base
     */
    public function broker(string $name): Base
    {
        $broker = $this->brokers[$name] ?? null;
        if ($broker !== null) {
            return $broker;
        }

        try {
            $class = $this->config->Brokers->getValue($name);
            if ($class === null) {
                $split = explode('\\', \Zer0\Brokers\Base::class);
                $split[count($split) - 1] = $name;
                $class = implode('\\', $split);
            }
            return $this->brokers[$name] = $broker = new $class($this);
        } finally {
            $broker->setNameIfEmpty($name);
        }
    }
}
