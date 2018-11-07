<?php

namespace Zer0\Session;

use PHPDaemon\Utils\Crypt;
use Zer0\Config\Section;
use Zer0\HTTP\HTTP;
use Zer0\Session\Storages\BaseAsync;

/**
 * Class SessionAsync
 * @package Zer0\Session
 */
class SessionAsync implements \ArrayAccess
{

    /**
     * @var string
     */
    protected $id;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var Section
     */
    protected $config;

    /**
     * @var Base
     */
    protected $storage;

    /**
     * @var HTTP
     */
    protected $http;

    /**
     * @var bool
     */
    protected $hasStarted = false;

    /**
     * @var array
     */
    protected $transaction = [];

    /**
     * Session constructor.
     * @param Section $config
     * @param BaseAsync $storage
     * @param HTTP $http
     */
    public function __construct(Section $config, BaseAsync $storage, HTTP $http)
    {
        $this->data = [];

        $this->config = $config;

        $this->storage = $storage;

        $this->http = $http;
    }

    /**
     * @return null|string ?string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param $cb
     */
    public function start($cb): void
    {
        if ($this->hasStarted) {
            $cb($this);
            return;
        }

        $this->startIfExists(function ($success) use ($cb) {
            if ($success) {
                $cb($this);
                return;
            }
            $this->generateId(function ($success) use ($cb) {
                if (!$success) {
                    $cb(false);
                    return;
                }
                $this->started();
                $cb($this);
            });
        });
    }

    /**
     * @param callable $cb
     */
    public function startIfExists($cb): void
    {
        if ($this->hasStarted) {
            $cb($this);
            return;
        }
        $id = (string)($_COOKIE[$this->config->name] ?? '');
        if ($id === '') {
            $cb(false);
            return;
        }
        $this->storage->read($id, function ($rawData) use ($id, $cb) {
            if (!$rawData) {
                $cb(false);
            }
            $this->id = $id;
            $this->data = [];
            $types = [];
            foreach ($rawData as $key => $value) {
                if (substr($key, 0, 2) === '$$') {
                    $this->data[substr($key, 2)] = $value;
                } elseif (substr($key, 0, 6) === '$type$') {
                    $types[substr($key, 6)] = $value;
                }
            }
            foreach ($types as $key => $type) {
                if (!isset($this->data[$key])) {
                    continue;
                }
                if ($type === 'i') {
                    $this->data[$key] = (int)$this->data[$key];
                } elseif ($type === 'f') {
                    $this->data[$key] = (float)$this->data[$key];
                } elseif ($type === 'b') {
                    $this->data[$key] = (bool)$this->data[$key];
                } elseif ($type === 'x') {
                    $this->data[$key] = unserialize($this->data[$key]);
                }
            }
            $this->started();
            $cb($this);
        });
    }

    /**
     * @return bool
     */
    protected function started(): bool
    {
        $this->hasStarted = true;
        $_SESSION = $this;
        return true;
    }

    /**
     * @return void
     */
    public function writeClose(): void
    {
        $this->write();
        $this->close();
    }

    /**
     * @param null $cb
     * @return void
     */
    public function write($cb = null): void
    {
        if ($this->id === null) {
            return;
        }
        $this->storage->transaction($this->id, $this->transaction, $cb);
        $this->transaction = [];
    }

    /**
     * @return void
     */
    public function close(): void
    {
        unset($_SESSION);
        $this->id = null;
        $this->transaction = [];
        $this->data = null;
    }

    /**
     * @param $cb
     */
    protected function generateId($cb): void
    {
        Crypt::randomBytes(24, function ($bytes) use ($cb) {
            $this->id = base64_encode($bytes);
            $this->http->setcookie(
                $this->config->name,
                $this->id,
                strtotime($this->config->expire),
                $this->config->path ?? '/',
                $this->config->domain ?? '',
                $this->config->secure ?? null,
                $this->config->httpOnly ?? true
            );
            $cb(true);
        });
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        $this->http->setcookie(
            $this->config->name,
            'deleted',
            1,
            $this->config->path ?? '/',
            $this->config->domain ?? '',
            $this->config->secure ?? null,
            $this->config->httpOnly ?? true
        );
        if ($this->id !== null) {
            $this->storage->destroy($this->id);
        }
        $this->id = null;
        $this->hasStarted = false;
        $this->data = [];
    }
    /**
     * @return bool
     * @throws \Exception
     */
    public function regenerateId(): bool
    {
        if (!$this->hasStarted) {
            return false;
        }
        $oldId = $this->id;
        $this->generateId();
        $this->storage->rename($oldId, $this->id);
        return true;
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function &offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
        if (is_integer($value)) {
            $this->transaction['$set']['$type$' . $offset] = 'i';
            unset($this->transaction['$unset']['$type$' . $offset]);
        } elseif (is_float($value)) {
            $this->transaction['$set']['$type$' . $offset] = 'f';
            unset($this->transaction['$unset']['$type$' . $offset]);
        } elseif (is_bool($value)) {
            $this->transaction['$set']['$type$' . $offset] = 'b';
            unset($this->transaction['$unset']['$type$' . $offset]);
        } elseif (!is_scalar($value)) {
            $this->transaction['$set']['$type$' . $offset] = 'x';
            unset($this->transaction['$unset']['$type$' . $offset]);
            $value = serialize($value);
        } else {
            $this->transaction['$unset']['$type$' . $offset] = true;
        }
        unset($this->transaction['$unset'][$offset]);
        unset($this->transaction['$incr'][$offset]);

        $this->transaction['$set']['$$' . $offset] = $value;
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset], $this->transaction['$set']['$$' . $offset]);
        $this->transaction['$unset']['$$' . $offset] = true;
        $this->transaction['$unset']['$type$' . $offset] = true;
        unset($this->transaction['$incr']['$$' . $offset]);
    }

    /**
     * @param string $offset
     * @param int $value
     * @return int
     */
    public function incr(string $offset, int $value = 1): int
    {
        if (!isset($this->data[$offset])) {
            $this[$offset] = 0;
        }
        $this->data[$offset] += $value;
        if (isset($this->transaction['$set']['$$' . $offset])) {
            $this->transaction['$set']['$$' . $offset] += $value;
        } else {
            $this->transaction['$incr']['$$' . $offset] = $value;
        }
        $this->transaction['$set']['$type$' . $offset] = 'i';
        return $this->data[$offset];
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
