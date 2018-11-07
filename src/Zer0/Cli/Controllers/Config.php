<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;
use Zer0\Cli\Exceptions\InvalidArgument;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Config\Section;

/**
 * Class Config
 * @package Zer0\Cli\Controllers
 */
final class Config extends AbstractController
{
    /**
     * @var string
     */
    protected $command = 'config';

    /**
     * @param mixed ...$args
     */
    public function treeAction(...$args): void
    {
        $key = $this->app->env;
        $value = $this->app->config;
        $filter = null;
        $i = 0;
        $count = count($args);
        foreach ($args as $arg) {
            ++$i;
            if (!preg_match('~^\w+$~', $arg)) {
                $filter = $arg;
                break;
            }
            $subValue = $value->{$arg};
            if ($subValue instanceof ConfigInterface) {
                $key = $arg;
                $value = $subValue;
            } elseif ($subValue !== null) {
                $filter = $arg;
                break;
            } else {
                throw new InvalidArgument($arg . ': key/section not found 😞');
            }
        }
        if ($i < $count) {
            throw new InvalidArgument($args[$i] . ': key/section not found 😞');
        }
        $this->drawLevel($key, $value, 0, 0, $filter);
    }

    /**
     * @param string $key
     * @param ConfigInterface $value
     * @param int $level
     * @param int $n
     * @param string $filter
     */
    protected function drawLevel(string $key, ConfigInterface $value, int $level, int $n = 0, string $filter = null): void
    {
        $styleScheme = [
            'tree' => 'fg(green)',
            'colon' => 'fg(green)',
            'section' => 'bold fg(blue)',
            'item' => 'bold fg(blue)',
            'value' => '',
        ];

        $this->cli->write(str_repeat("|\t", $level) . ($level > 0 ? ($n > 0 ? '|' : '\\') . str_repeat(
            '—',
                    $level + 1
        ) . ' ' : ''), $styleScheme['tree']);
        $this->cli->writeln($key, $styleScheme['section']);
        $i = 0;
        foreach ($value->sectionsList() as $subKey) {
            $this->drawLevel($subKey, $value->{$subKey}, $level + 1, $i);
            ++$i;
        }
        if ($value instanceof Section) {
            ++$level;
            $first = true;
            foreach ($value->toArray() as $subKey => $subValue) {
                if ($filter !== null) {

                    if (!fnmatch($filter, $subKey)) {
                        continue;
                    }
                }
                if (!$first) {
                    $this->cli->write('|', $styleScheme['tree']);
                    $this->cli->writeln('');
                } else {
                    $first = false;
                }

                $this->cli->write(str_repeat("|\t", $level) . ($level > 0 ? ($i > 0 ? '|' : '\\') . str_repeat(
                    '—',
                            $level + 1
                ) . ' ' : ''), $styleScheme['tree']);

                $this->cli->write($subKey, $styleScheme['item']);
                $this->cli->write(': ', $styleScheme['colon']);

                $this->cli->colorfulJson($subValue);

                $this->cli->writeln('');

                $this->cli->write(str_repeat("|\t", $level), $styleScheme['tree']);

                ++$i;
            }

            if (!$first) {
                $this->cli->writeln('');
            }
        }
    }
}
