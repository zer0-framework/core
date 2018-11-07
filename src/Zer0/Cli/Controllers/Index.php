<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;
use Zer0\Cli\Exceptions\InternalRedirect;
use Zer0\Cli\Exceptions\NotFound;

/**
 * Class Index
 * @package Zer0\Cli\Controllers
 */
final class Index extends AbstractController
{


    /**
     * @throws InternalRedirect
     */
    public function indexAction(): void
    {
        $commands = $this->cli->config->Commands->toArray();
        $this->readline(
            function (array $parts) use ($commands): void {
                $this->cli->route(implode(' ', $parts));
            },
            function (string $prefix): array {
                if ($prefix === '') {
                    return array_merge($this->getActions(), $this->specialCommands);
                }
                if (in_array($prefix, $this->getCommandsList())) {
                    $controllerClass = $this->cli->config->Commands->{$prefix}['controller'] ?? null;
                    if ($controllerClass === null) {
                        return [];
                    }
                    try {
                        return $this->cli->instantiateController($controllerClass)->getActions();
                    } catch (NotFound $e) {
                    }
                }
                return [];
            }
        );
    }

    /**
     * @return array
     */
    public function getCommandsList(): array
    {
        $ret = [];
        $commands = $this->cli->config->Commands->toArray();
        foreach ($commands as $name => $item) {
            if ($name === '_') {
                continue;
            }
            $ret[] = $name;
        }
        return $ret;
    }

    /**
     * @return array
     */
    public function getActions(): array
    {
        return array_merge($this->getCommandsList(), parent::getActions());
    }

    /**
     *
     */
    public function helpAction(): void
    {
        $this->cli->writeln('USAGE: ' . basename($_SERVER['argv'][0]) . ' [command]');
        foreach ($this->getActions() as $name) {
            $this->cli->writeln("\t👉\t" . $name);
        }
    }

    /**
     *
     */
    public function testAction()
    {
        //passthru('./cli/vendor/bin/fastest -x phpunit.xml "./cli/vendor/bin/phpunit {};"');
        passthru('./cli/vendor/bin/phpunit');
    }
}
