<?php

namespace Zer0\HTTP\Traits;

use Zer0\HTTP\Router\Basic;

/**
 * Trait Router
 * @package Zer0\HTTP\Traits
 */
trait Router
{
    /**
     * @var Basic
     */
    protected $router;

    /**
     * @return void
     * @throws \Zer0\HTTP\Exceptions\MethodNotAllowed
     */
    public function routeRequest(): void
    {
        if ($this->router === null) {
            $this->router = new Basic($this->config->Routes->toArray());
        }
        $this->router->execute();
    }
}
