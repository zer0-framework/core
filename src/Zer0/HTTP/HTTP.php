<?php
declare(strict_types=1);

namespace Zer0\HTTP;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\HTTP\Traits\Files;
use Zer0\HTTP\Traits\Handlers;
use Zer0\HTTP\Traits\Helpers;
use Zer0\HTTP\Traits\Pjax;
use Zer0\HTTP\Traits\Router;
use Zer0\Traits\EventHandlers;

/**
 * Class HTTP
 * @package Zer0\HTTP
 */
class HTTP
{
    use EventHandlers;
    use Pjax;
    use Helpers;
    use Handlers;
    use Router;
    use Files;

    /**
     * @var App
     */
    public $app;

    /**
     * @var ConfigInterface
     */
    public $config;


    /**
     * HTTP constructor.
     * @param ConfigInterface $config
     * @param App $app
     */
    public function __construct(ConfigInterface $config, App $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * @return void
     */
    public function prepareEnv(): void
    {
        if (!$_GET && ($query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY)) !== null) {
            parse_str($query, $_GET);
        }
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'DEFAULT_', 8) !== 0) {
                continue;
            }
            $realKey = substr($key, 8);
            if (($_SERVER[$realKey] ?? '') === '') {
                $_SERVER[$realKey] = $value;
            }
            unset($_SERVER[$key]);
        }
        if (isset($_SERVER['ROUTE_ACTION'])) {
            $_SERVER['ROUTE_ACTION'] = str_replace(' ', '', ucwords(str_replace('-', ' ', $_SERVER['ROUTE_ACTION'])));
        }
    }
}
