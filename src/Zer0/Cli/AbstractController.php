<?php

namespace Zer0\Cli;

use Hoa\Console\Cursor;
use Hoa\Console\Readline\Autocompleter\Word;
use Hoa\Console\Readline\Readline;
use Zer0\App;
use Zer0\Cli\Exceptions\InternalRedirect;
use Zer0\Cli\Intefarces\ControllerInterface;

/**
 * Class AbstractController
 * @package Zer0\Cli
 */
abstract class AbstractController implements ControllerInterface
{

    /**
     * @var \Zer0\App
     */
    protected $app;

    /**
     * @var Cli
     */
    protected $cli;

    /**
     * @var string
     */
    protected $command = '';

    /**
     * @var string
     */
    protected $historyFile;

    /**
     * @var array
     */
    protected $specialCommands = ['help', 'history', 'quit'];

    /**
     * @var float
     */
    protected $timestamp;

    /**
     * AbstractController constructor.
     * @param Cli $cli
     * @param \Zer0\App $app
     */
    public function __construct(Cli $cli, App $app)
    {
        $this->app = $app;
        $this->cli = $cli;
        $this->historyFile = $_SERVER['HOME'] . '/.zer0_history';
    }

    /**
     *
     */
    public function historyAction(): void
    {
        echo 123 . PHP_EOL;
        readfile($this->historyFile);
    }

    /**
     *
     */
    public function helpAction(): void
    {
        echo 'USAGE: ' . basename($_SERVER['argv'][0]) . ' ' . $this->command . ' [command]' . PHP_EOL;

        foreach ($this->getActions() as $action) {
            echo "\t👉\t" . $action . PHP_EOL;
        }

        echo PHP_EOL;
    }

    /**
     * @return array
     */
    public function getActions(): array
    {
        $actions = [];
        foreach (get_class_methods('\\' . static::class) as $method) {
            if (substr($method, -6) !== 'Action') {
                continue;
            }
            $action = lcfirst(substr($method, 0, -6));
            if (in_array($action, $this->specialCommands) || $action === 'index') {
                continue;
            }
            $actions[] = $action;
        }
        return $actions;
    }

    /**
     * @throws InternalRedirect
     */
    public function indexAction(): void
    {
        $this->readline(
            function ($parts) {
                $this->cli->handleCommand('\\' . static::class, $parts[0], array_splice($parts, 1));
            },
            function (string $prefix): array {
                if ($prefix === '') {
                    return array_merge($this->getActions(), $this->specialCommands);
                }
                return [];
            }
        );
    }

    /**
     * @param callable $callback
     * @param callable $getAutocompleteWords
     * @throws InternalRedirect
     */
    public function readline(callable $callback, callable $getAutocompleteWords): void
    {
        $rl = new class($this, $this->historyFile, $getAutocompleteWords) extends Readline {
            /**
             * @var AbstractController
             */
            protected $controller;

            /**
             * @var string
             */
            protected $historyFile;

            /**
             *  constructor.
             * @param AbstractController $controller
             * @param string $historyFile
             * @param callable $getAutocompleteWords
             */
            public function __construct(AbstractController $controller, string $historyFile, callable $getAutocompleteWords)
            {
                parent::__construct();
                $this->controller = $controller;
                $this->historyFile = $historyFile;
                $oldMapping = $this->_mapping["\t"];
                $this->_mapping["\t"] = function (Readline $rl) use ($oldMapping, $getAutocompleteWords) {
                    if (!strlen($rl->getLine())) {
                        \Hoa\Console\Cursor::clear('line');

                        foreach ($getAutocompleteWords('') as $word) {
                            echo $word . "\t";
                        }
                        echo PHP_EOL;

                        echo PHP_EOL;
                        echo $this->_prefix;
                        return self::STATE_NO_ECHO;
                    }
                    return $oldMapping($rl);
                };
                $oldMapping = $this->_mapping["\n"];
                $this->_mapping["\n"] = function (Readline $self) use ($oldMapping) {
                    $line = $self->getLine();
                    if (ctype_space(substr($line, 0, 1))) {
                        return static::STATE_BREAK;
                    }
                    file_put_contents($this->historyFile, $line . PHP_EOL, FILE_APPEND);
                    return $oldMapping($self);
                };

                if (is_file($this->historyFile)) {
                    foreach (explode(PHP_EOL, file_get_contents($this->historyFile)) as $item) {
                        $this->addHistory($item);
                    }
                }
            }
        };
        $autocompleter = new class([]) extends Word {
            /**
             * @var callable
             */
            protected $wordsCallback;

            /**
             * @param callable $callback
             */
            public function setWordsCallback(callable $callback): void
            {
                $this->wordsCallback = $callback;
            }

            /**
             * Get definition of a word.
             */
            public function getWordDefinition(): string
            {
                return '\b\w+';
            }

            /**
             * @return mixed
             */
            public function getWords(): array
            {
                return call_user_func($this->wordsCallback);
            }
        };
        $autocompleter->setWordsCallback(function () use ($getAutocompleteWords, $rl): array {
            $prefix = preg_replace('~(?:\s+|^)\S*$~', '', $rl->getLine());

            return $getAutocompleteWords($prefix);
        });

        $rl->setAutocompleter($autocompleter);
        for (; ;) {
            $input = trim($rl->readLine($this->command . '> '));
            if ($input === '') {
                continue;
            }
            if ($input === '..') {
                throw (new InternalRedirect)->route('_');
            }
            if ($input === 'q' || $input === 'quit') {
                posix_kill(posix_getpid(), SIGINT);
                continue;
            }
            $parts = preg_split('~\s+~', $input);
            if (in_array(strtolower($parts[0]), ['help', '?'])) {
                $this->helpAction();
                continue;
            }
            $callback($parts);
        }
    }

    /**
     *
     */
    public function before(): void
    {
        $this->timestamp = microtime(true);
    }

    /**
     * @param int $precision
     * @return float|int
     */
    public function elapsedMill(int $precision = 0)
    {
        return round((microtime(true) - $this->timestamp) * 1000, $precision);
    }

    /**
     *
     */
    public function after(): void
    {
    }

    /**
     * @param mixed $response
     */
    public function renderResponse($response): void
    {
    }
}
