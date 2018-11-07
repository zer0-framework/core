<?php

namespace Zer0\HTTP;

use Zer0\Model\Exceptions\BundleException;
use Zer0\Model\Exceptions\InvalidStateException;
use Zer0\Model\Validator;

/**
 * Class Input
 * @package Zer0\HTTP
 */
class Input implements \ArrayAccess
{
    use Validator;

    /** @var \Zer0\Model\Exceptions\BundleException[] Array of bundled exceptions */
    protected $exceptionsBundle = [];

    protected $data = [];

    /**
     * If true, object is in read-only mode
     * @var boolean
     */
    protected $publicReadonly = false;

    /**
     * @var bool
     */
    protected $new = true;

    /**
     * Input constructor.
     * @param array|null $sources
     * @throws \Exception
     */
    public function __construct(?array $sources = null)
    {
        if ($sources !== null) {
            $this($sources);
        }
    }


    /**
     * Sets read-only mode for public-visibility calls.
     * Useful when passing Model objects into unsafe environments like template engines.
     *
     * @param  boolean $bool On/off
     * @return Input $this
     */
    public function publicReadonly($bool = true)
    {
        $this->publicReadonly = (bool)$bool;

        return $this;
    }


    /**
     * Set a property of current model
     *
     * @param string $field Property
     * @param mixed $value Value
     * @return $this
     */
    protected function set($field, $value)
    {
        if ($value === null) {
            unset($this[$field]);
            return $this;
        }
        if ($this->data !== null) {
            $this->data[$field] = $value;
        }

        return $this;
    }

    /**
     * Getter for properties, returns value of the $field
     *
     * @param $field
     * @return bool|mixed
     * @throws InvalidStateException
     */
    public function __get($field)
    {
        $method = 'get' . ucfirst($field);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        if ($this->data === null && !$this->new) {
            throw new InvalidStateException('record data is not loaded');
        }
        return $this->data[$field] ?? null;
    }

    /**
     * __set()
     *
     * @param $field
     * @param $value
     * @return void
     */
    public function __set($field, $value)
    {
        $method = 'set' . ucfirst($field);
        $this->$method($value);
    }

    /**
     * Enter the exceptions bundling mode
     *
     * @return Input $this
     */
    public function bundleExceptions()
    {
        if ($this->exceptionsBundle === null) {
            $this->exceptionsBundle = [];
        }
        return $this;
    }

    /**
     * @param array|null $sources
     * @return Input
     * @throws \Exception
     */
    public function __invoke(?array $sources = null): self
    {
        if ($sources !== null) {
            $this->new = true;
            foreach (array_merge(...$sources) as $key => $value) {
                $this[$key] = $value;
            }
        }
        $this->validate();

        if ($this->exceptionsBundle) {
            $bundle = (new BundleException)->bundle($this->exceptionsBundle);
            $this->exceptionsBundle = null;
            throw $bundle;
        }

        $this->new = false;

        return $this;
    }


    /**
     * Checks if property exists
     * @param string $field
     * @return boolean Exists?
     */
    public function offsetExists($field)
    {
        return $this[$field] !== null;
    }

    /**
     * Get property by name
     * @param string $field
     * @return mixed|null
     * @throws InvalidStateException
     */
    public function offsetGet($field)
    {
        $method = 'get' . ucfirst($field);
        if ($method === 'getIterator') { // getIterator() is reserved by \IteratorAggregate
            $method = '_getIterator';
        }
        if (strpos($method, ':') !== false) {
            $args = explode(':', $method);
            $method = array_shift($args);
            if (!method_exists($this, $method)) {
                return null;
            }
            return $this->$method(...$args);
        } elseif (method_exists($this, $method)) {
            return $this->$method();
        }
        if ($this->data === null && !$this->new) {
            throw new InvalidStateException();
        }
        if (isset($this->joinedData[$field])) {
            return $this->joinedData[$field];
        }
        return $this->data[$field] ?? null;
    }

    /**
     * Set property
     *
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws \Exception
     */
    public function offsetSet($field, $value)
    {
        $method = 'set' . ucfirst($field);
        try {
            $this->$method($value);
        } catch (\Exception $e) {
            if ($this->exceptionsBundle === null) {
                throw $e;
            } else {
                if (method_exists($e, 'getKey')) {
                    $this->exceptionsBundle[$e->getKey()] = $e;
                } else {
                    $this->exceptionsBundle[] = $e;
                }
            }
        }
    }

    /**
     * Unset field
     *
     * @param string $field
     * @return void
     */
    public function offsetUnset($field)
    {
        $method = 'unset' . ucfirst($field);
        $this->$method();
    }


    /**
     * @param $method
     * @param $args
     * @return $this|bool|null
     * @throws InvalidStateException
     */
    public function __call($method, $args)
    {
        // get{Field}() returns $data value for the specified field
        if (strncmp($method, 'get', 3) === 0) {
            $field = lcfirst(substr($method, 3));
            if ($this->data === null) {
                throw new InvalidStateException('record data is not loaded');
            }
            return isset($this->data[$field]) ? $this->data[$field] : null;
        }

        // set{Field}() sets $data value for specified field
        if (strncmp($method, 'set', 3) === 0) {
            if ($this->publicReadonly) {
                return $this;
            }
            if (method_exists($this, $method)) {
                $this->$method(...$args);
            } else {
                $this->setProperty(lcfirst(substr($method, 3)), count($args) ? $args[0] : null);
            }
            return $this;
        }
        if (strncmp($method, 'is', 2) === 0) {
            return (bool)$this[lcfirst(substr($method, 2))];
        }
        return null;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }
}
