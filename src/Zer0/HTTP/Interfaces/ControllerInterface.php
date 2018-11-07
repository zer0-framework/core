<?php

namespace Zer0\HTTP\Intefarces;

use Zer0\App;
use Zer0\HTTP\HTTP;

/**
 * Interface ControllerInterface
 * @package Zer0\HTTP\Intefarces
 */
interface ControllerInterface
{
    /**
     * ControllerInterface constructor.
     * @param HTTP $http
     * @param App $app
     */
    public function __construct(HTTP $http, App $app);
    public function before(): void;
    public function after(): void;
}
