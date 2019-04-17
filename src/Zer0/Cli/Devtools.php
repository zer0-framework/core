<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;

/**
 * Class Build
 * @package Zer0\Cli\Controllers
 */
final class Devtools extends AbstractController
{
    /**
     * @var string
     */
    protected $command = 'devtools';

    /**
     *
     */
    public function phpStormMetaAction(): void
    {
        $destfile = ZERO_ROOT . '/.phpstorm.meta.php';

        $brokers = $this->app->config->Brokers->toArray();

        $autoload = include('vendor/composer/autoload_classmap.php');

        foreach ($autoload as $class => $path) {
            if (preg_match('~^Zer0\\\\Brokers\\\\(\\w+)~', $class, $match)) {
                $name = $match[1];
                $class = '\\'. $match[0];
                if (!isset($brokers[$name]) && $name !==  'Base') {
                    $brokers[$name] = $class;
                }
            }
        }

        $set = $override =  '';
        foreach ($brokers as $name => $class) {
            $name = var_export($name, true);
            $set .= "        {$name},\n";
            $override .= "        {$name} => {$class}::class,\n";
        }
        $set = rtrim($set);
        $override = rtrim($override);

        $meta = "<?php
namespace PHPSTORM_META {

    registerArgumentsSet(
        'brokers',
{$set}
    );

    expectedArguments(\Zer0\AppTraits\Brokers::broker(), 0, argumentsSet('brokers'));

    override(\Zer0\AppTraits\Brokers::factory(0), map([
        '' => '@',
{$override}
    ]));
}
";
        file_put_contents($destfile, $meta);
        $this->cli->successLine("Written to $destfile in " . $this->elapsedMill() . " ms.");
    }

    public function dumpAction(): void
    {
        $this->phpStormMetaAction();
    }
}
