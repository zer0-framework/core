<?php

namespace Zer0\Cli;

use Hoa\Console\Cursor;
use Zer0\App;
use Zer0\Cli\Controllers\Index;
use Zer0\Cli\Exceptions\CliError;
use Zer0\Cli\Exceptions\InternalRedirect;
use Zer0\Cli\Exceptions\InvalidArgument;
use Zer0\Cli\Exceptions\NotFound;
use Zer0\Cli\Intefarces\ControllerInterface;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Cli
 * @package Zer0\Cli
 */
class Cli
{
    /**
     * @var bool
     */
    protected $interactiveMode = false;

    /**
     * @var bool
     */
    protected $colorize = true;


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
     * Change the process title
     * @param string $title
     */
    public function setProcTitle($title = null): void
    {
        cli_set_process_title(implode(' ', $_SERVER['argv']) . ($title !== null ? ' (' . $title . ')' : ''));
        print "\033]0;$title\007";
    }

    /**
     * @param bool|null $mode
     * @return bool
     */
    public function interactiveMode(?bool $mode = null): bool
    {
        if ($mode === null) {
            return $this->interactiveMode;
        }
        return $this->interactiveMode = $mode;
    }

    /**
     *
     */
    public function listenToSignals(): void
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            if ($this->interactiveMode) {
                $this->interactiveMode = false;
            } else {
                echo PHP_EOL . 'Bye! 👋' . PHP_EOL;
                exit(0);
            }
        }, false);
    }

    /**
     * @param null|string $command
     */
    public function route(?string $command = null)
    {
        if ($command !== null) {
            $args = preg_split('~\s+~', $command);
            array_unshift($args, $_SERVER['argv'][0]);
        } else {
            $args = $_SERVER['argv'];
        }
        array_shift($args);
        $command = array_shift($args) ?? '_';
        if ($command === null) {
            $command = 'index';
        }
        $route = $this->config->Commands->{lcfirst($command)} ?? null;
        if ($route) {
            $route['action'] = array_shift($args);
            $route['action'] = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $route['action']))));
        } else {
            $route = [
                'controller' => '\\' . Index::class,
                'action' => $command,
            ];
        }
        $this->handleCommand($route['controller'], $route['action'] ?? 'index', $args);
    }

    /**
     * @param string $controllerClass
     * @return AbstractController
     * @throws NotFound
     */
    public function instantiateController(string $controllerClass): AbstractController
    {
        if (substr($controllerClass, 0, 1) !== '\\') {
            $controllerClass = $this->config->default_controller_ns . '\\' . $controllerClass;
        }

        if (!class_exists($controllerClass) || !class_implements($controllerClass, ControllerInterface::class)) {
            throw new NotFound('Controller ' . $controllerClass . ' not found 😞');
        }

        return new $controllerClass($this, $this->app);
    }

    /**
     * @param string $controllerClass
     * @param string $action
     * @param array $args
     * @return void
     */
    public function handleCommand(string $controllerClass, string $action, array $args = []): void
    {
        try {
            if ($controllerClass === '') {
                throw new NotFound('$controllerClass cannot be empty');
            }
            if ($action === '') {
                $action = 'index';
            }

            $method = strtolower($action) . 'Action';

            $controller = $this->instantiateController($controllerClass);

            if (!method_exists($controller, $method)) {
                throw new NotFound($action . ': command not found 😞');
            }

            $controller->action = $method;
            $controller->before();
            $ret = $controller->$method(...$args);
            if ($ret !== null) {
                $controller->renderResponse($ret);
            }
            $controller->after();
        } catch (NotFound $exception) {
            echo $exception->getMessage() . PHP_EOL;
        } catch (CliError $error) {
            $this->handleException($error);
        } catch (InternalRedirect $redirect) {
            if ($redirect->command !== null) {
                $this->route($redirect->command);
            } else {
                $this->handleCommand($redirect->controller, $redirect->action, $redirect->args);
            }
        } catch (\Throwable $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * @param \Throwable $exception
     */
    public function handleException(\Throwable $exception): void
    {
        if ($exception instanceof InvalidArgument) {
            $this->writeln($exception->getMessage());
            return;
        }
        $this->write('Uncaught exception:', 'fg(white) bg(red)');
        $this->writeln(' ' . (string)$exception);
        Cursor::bip();
    }


    /**
     * @param string $text
     * @param string|null $style
     */
    public function write(string $text, string $style = null): void
    {
        if (!$this->colorize) {
            $style = null;
        }
        if ($style !== null) {
            Cursor::colorize($style);
        }
        echo $text;

        if ($style !== null) {
            Cursor::colorize('n');
        }
    }


    /**
     * @param string $text
     * @param string|null $style
     */
    public function writeln(string $text, string $style = null): void
    {
        if (!$this->colorize) {
            $style = null;
        }

        echo $text . PHP_EOL;

        if ($style !== null) {
            Cursor::colorize('n');
        }
    }

    /**
     * @param string $line
     */
    public function successLine(string $line): void
    {
        $this->write('√', 'fg(green)');
        echo ' ' . $line . PHP_EOL;
    }

    /**
     * @param string $line
     */
    public function errorLine(string $line): void
    {
        $this->write('X', 'fg(red)');
        echo ' ' . $line . PHP_EOL;
    }

    /**
     * @param mixed $var
     * @param string $style
     */
    public function colorfulJson($var, ?string $style = null): void
    {
        $styleScheme = [
            'bracket' => 'fg(green)',
            'quote' => 'fg(green)',
            'comma' => 'fg(green)',
            'colon' => 'fg(green)',
            'key' => 'underlined fg(blue)',
            'key_scalar' => 'fg(blue)',
            'string' => '',
            'integer' => '',
        ];

        if ($var === null) {
            $this->write('null');
        }
        elseif (is_scalar($var)) {
            if (is_string($var)) {
                $this->write('"', $styleScheme['quote']);
                $this->write(
                    substr(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1),
                    $style ?? $styleScheme[gettype($var)]
                );
                $this->write('"', $styleScheme['quote']);
            } else {
                $this->write(
                    json_encode($var),
                    $style ?? $styleScheme[gettype($var)] ?? ''
                );
            }
        } else {
            $isArray = is_array($var) && count(array_filter(array_keys($var), 'is_string')) === 0;

            $this->write($isArray ? '[' : '{', $styleScheme['bracket']);
            $i = 0;

            foreach ($var as $key => $value) {
                if ($i > 0) {
                    $this->write(', ', $styleScheme['comma']);
                }
                if (!$isArray) {
                    $this->colorfulJson($key, (is_scalar($value) || is_null($value)) ? $styleScheme['key_scalar'] : $styleScheme['key']);
                    $this->write(': ', $styleScheme['colon']);
                }
                $this->colorfulJson($value);
                ++$i;
            }
            $this->write($isArray ? ']' : '}', $styleScheme['bracket']);
        }
    }
}
