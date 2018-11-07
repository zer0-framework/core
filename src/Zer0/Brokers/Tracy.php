<?php

namespace Zer0\Brokers;

use Tracy\Debugger;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Helpers\ErrorsAreExceptions;

/**
 * Class Tracy
 * @package Zer0\Brokers
 */
class Tracy extends Base
{
    /**
     * @param ConfigInterface $config
     * @return object
     */
    public function instantiate(ConfigInterface $config)
    {
        $mode = $config->mode ?? 'off';
        if ($mode === 'off') {
            return;
        }

        /**
         * @var \Zer0\HTTP\HTTP $http
         */
        $http = $this->app->broker('HTTP')->get();

        if ($mode === 'auth') {
            $param = $config->secret_param ?? 'tracy-debug';
            $secret = $_GET[$param] ?? $_COOKIE[$param] ?? '';
            if (!strlen($secret) || !strlen($config->secret ?? '') || !hash_equals($config->secret, $secret)) {
                return;
            }

            if ($http !== null) {
                $http->setcookie($param, $secret, 0, '/', '', null, true);
            }
        } elseif ($mode !== 'on') {
            // Unknown mode
            return;
        }

        Debugger::$showBar = false;
        Debugger::$scream = true;
        Debugger::$strictMode = true;
        Debugger::$disableHandlers = true;
        Debugger::enable(false);

        foreach ($config->panels ?? [] as $panel) {
            Debugger::getBar()->addPanel(new $panel);
        };
        ErrorsAreExceptions::makeItSo();

        if ($http !== null) {
            $http->setExceptionHandler(function (\Throwable $exception) use ($http) {
                if ($http->isAjaxRequest()) {
                    Debugger::fireLog($exception);
                } else {
                    ob_clean();
                    Debugger::exceptionHandler($exception, false);
                }
            });
            $http->on('endRequest', function () {
                if (!Debugger::$productionMode) {
                    Debugger::getBar()->render();
                }
            });
        }


        return new class {
            /**
             * @param $panel
             */
            public function addPanel($panel)
            {
                Debugger::getBar()->addPanel($panel);
            }

            /**
             *
             */
            public function renderBar()
            {
                //   Debugger::getBar()->render();
            }
        };
    }
}
