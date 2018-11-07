<?php

namespace Zer0\HTTP\Exceptions;

/**
 * Class Redirect
 * @package Zer0\HTTP\Exceptions
 */
abstract class Redirect extends \Exception
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param string $url
     * @return Redirect
     */
    public static function url(string $url): self
    {
        return (new static)->setUrl($url);
    }

    /**
     * @return Redirect
     */
    public static function back()
    {
        return (new static)->setUrl($_REQUEST['backUrl'] ?? $_SERVER['HTTP_REFERER'] ?? '/');
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
