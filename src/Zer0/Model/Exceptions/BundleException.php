<?php

namespace Zer0\Model\Exceptions;

use Zer0\Exceptions\BaseException;

/**
 * Class BundleException
 * @package Zer0\Model\Exceptions
 */
class BundleException extends BaseException implements \Iterator
{
    protected $exceptions = [];
    protected $pos = 0;


    /**
     * @param array $bundle
     * @return $this
     */
    public function bundle(array $bundle)
    {
        $this->exceptions = $bundle;
        return $this;
    }

    /**
     * @return array
     */
    public function validationErrors()
    {
        return $this->gather(ValidationErrorException::class);
    }

    /**
     * Gather exceptions of a given type
     *
     * @param $class
     * @param bool|false $overwrite
     * @return array
     */
    public function gather($class, $overwrite = false)
    {
        $ret = [];
        foreach ($this->exceptions as $e) {
            if ($e instanceof $class) {
                if ($e instanceof Interfaces\Gatherable) {
                    if ($overwrite) {
                        $ret[$e->getKey()] = $e->getInfo();
                    } else {
                        $key = $e->getKey();
                        if (isset($ret[$key])) {
                            if (isset($ret[$key][0])) {
                                $ret[$key][] = $e->getInfo();
                            } else {
                                $ret[$key] = [$ret[$key], $e->getInfo()];
                            }
                        } else {
                            $ret[$key] = $e->getInfo();
                        }
                    }
                } else {
                    $ret[] = $e;
                }
            }
        }
        return $ret;
    }

    /**
     * Current object
     * @return object Model
     */
    public function current()
    {
        return isset($this->exceptions[$this->pos]) ? $this->exceptions[$this->pos] : false;
    }

    /**
     * Current object
     * @return void Model
     */
    public function next()
    {
        ++$this->pos;
    }

    /**
     * valid()
     * @return boolean
     */
    public function valid()
    {
        return isset($this->exceptions[$this->pos]);
    }

    /**
     * Current key
     * @return integer
     */
    public function key()
    {
        return $this->pos;
    }

    /**
     * Rewind the pointer
     * @return  void
     */
    public function rewind()
    {
        $this->pos = 0;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    /**
     * @param bool $bool
     * @return $this
     */
    /**
     * @param bool $bool
     * @return $this
     */
    public function asArray($bool = true)
    {
        $this->asArray = (bool)$bool;
        return $this;
    }
}
