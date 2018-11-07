<?php

namespace Zer0\HTTP;

use Zer0\App;
use Zer0\Exceptions\TemplateNotFoundException;
use Zer0\HTTP\Exceptions\Forbidden;
use Zer0\HTTP\Exceptions\HttpError;
use Zer0\HTTP\Exceptions\InternalRedirect;
use Zer0\HTTP\Exceptions\Redirect;
use Zer0\HTTP\Intefarces\ControllerInterface;
use Zer0\HTTP\Responses\Base;
use Zer0\HTTP\Responses\JSON;
use Zer0\HTTP\Responses\Template;

/**
 * Class AbstractController
 * @package Zer0\HTTP
 */
abstract class AbstractController implements ControllerInterface
{

    /**
     * @var App
     */
    protected $app;

    /**
     * @var HTTP
     */
    protected $http;

    /**
     * @var string
     */
    public $action;

    /**
     * Config constructor.
     * @param HTTP $http
     * @param App $app
     */
    public function __construct(HTTP $http, App $app)
    {
        $this->app = $app;
        $this->http = $http;
    }


    /**
     * @var \Quicky
     */
    protected $tpl;

    /**
     * @var bool
     */
    protected $skipOriginCheck = false;

    /**
     * @param string $name
     * @return mixed
     */
    public function sessionStart(string $name = '')
    {
        return $this->app->broker('Session')->get($name)->start();
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function sessionStartIfExists(string $name = ''): bool
    {
        return $this->app->broker('Session')->get($name)->startIfExists();
    }

    /**
     * @param string $layout
     * @return string
     */
    public function pjaxVersion(string $layout): string
    {
        $version = $layout . ':' . $this->app->buildTimestamp;
        if ($this->http->isPjaxRequest()) {
            $this->http->setPjaxVersion($version);
        }
        return $version;
    }

    /**
     * @throws Forbidden
     */
    public function before(): void
    {
        if (!$this->skipOriginCheck) {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET' || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $checkOrigin = $this->http->checkOrigin();

                if (!$checkOrigin || !$this->app->broker('CSRF_Token')->get()->validate()) {
                    $this->app->log(
                        'csrf check failed'
                    );
                    throw new Forbidden('Bad origin.');
                }
            }
        }
    }

    /**
     *
     */
    public function after(): void
    {
    }

    /**
     * @param string $file Template to render
     * @param array $scope = [] Variables
     * @return string Output
     * @throws TemplateNotFoundException
     */
    protected function fetchTemplate(string $file, array $scope = []): string
    {
        $this->tpl = $this->app->broker('Quicky')->get();
        $this->beforeRenderTemplate();
        $this->tpl->assign($scope);
        if (!$this->tpl->template_exists($file)) {
            throw new TemplateNotFoundException($file);
        }
        return $this->tpl->fetch($file);
    }

    /**
     * @param $response
     * @throws TemplateNotFoundException
     */
    public function renderResponse($response): void
    {
        if (!$response instanceof Base) {
            $response = new JSON($response);
        } elseif ($response instanceof Template) {
            $response->setController($this);
        }
        $response->render($this->http);
    }
}
