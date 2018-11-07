<?php

namespace Zer0\HTTP\Responses;

use Zer0\Exceptions\TemplateNotFoundException;
use Zer0\HTTP\HTTP;
use Zer0\HTTP\Intefarces\ControllerInterface;

/**
 * Class Template
 * @package Zer0\HTTP\Responses
 */
class Template extends Base
{

    /**
     * @var array
     */
    protected $scope = [];

    /**
     * @var string
     */
    protected $file;

    /**
     * @var \Zer0\HTTP\Intefarces\ControllerInterface
     */
    protected $controller;

    /**
     * @var \Quicky
     */
    protected $tpl;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * Template constructor.
     * @param string $file
     * @param array $scope = []
     */
    public function __construct(string $file, $scope = [])
    {
        $this->file = $file;
        $this->scope = $scope;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     * @return $this
     */
    public function assign($key, $value = null)
    {
        if (is_array($key)) {
            $this->scope = array_merge($this->scope, $key);
        } else {
            $this->scope[$key] = $value;
        }
        return $this;
    }

    /**
     * @param ControllerInterface $controller
     * @return $this
     */
    public function setController(ControllerInterface $controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * @param callable $callback
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Base constructor.
     * @param HTTP $http
     * @throws TemplateNotFoundException
     */
    public function render(HTTP $http)
    {
        $app = $http->app;
        $tpl = $app->broker('Quicky')->get();

        $tpl->assign($this->scope);

        $tpl->assign([
            'env' => $app->env,
            'isPjax' => $http->isPjaxRequest(),
            'csrfToken' => $app->broker('CSRF_Token')->get()->get(),
            'buildTimestamp' => $app->buildTimestamp,
            'tracy' => $app->broker('Tracy')->get(),
        ]);


        if ($this->callback !== null) {
            call_user_func($this->callback, $tpl);
        }

        $tpl->register_function('url', [$http, 'buildUrl']);

        if (!$tpl->template_exists($this->file)) {
            throw new TemplateNotFoundException($this->file);
        }
        $tpl->display($this->file);
    }
}
