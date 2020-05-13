<?php

namespace Zer0\Cli\Controllers;

use Zer0\Cli\AbstractController;

/**
 * Class Build
 *
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
    public function phpStormMetaAction (): void
    {
        $destfile = ZERO_ROOT . '/.phpstorm.meta.php';

        $brokers = $this->app->config->Brokers->toArray();

        $autoload = include('vendor/composer/autoload_classmap.php');

        foreach ($autoload as $class => $path) {
            if (preg_match('~^Zer0\\\\Brokers\\\\(\\w+)~', $class, $match)) {
                $name  = $match[1];
                $class = '\\' . $match[0];
                if (!isset($brokers[$name]) && $name !== 'Base') {
                    $brokers[$name] = $class;
                }
            }
        }

        $set = $override = $overrideFactory = '';
        foreach ($brokers as $name => $class) {

            $eName       = var_export($name, true);
            $set         .= "        {$eName},\n";
            $override    .= "        {$eName} => {$class}::class,\n";
            $refClass    = new \ReflectionClass($class);
            $returnClass = '\\' . (
                    $refClass->getMethod('get')->getReturnType()
                    ?? $refClass->getMethod('instantiate')->getReturnType()
                );

            $getReturn = function (string $method) use ($refClass): ?string {
                $phpdoc = $refClass->getMethod('instantiate')->getDocComment();
                if (preg_match('~@return (\S+)~', $phpdoc, $match)) {
                    return $match[1];
                }

                return null;
            };
            if ($returnClass === '\\') {
                $returnClass = $getReturn('get') ?? $getReturn('instantiate') ?? '\\';
            }

            if ($returnClass === '\\') {
                continue;
            }
            $overrideFactory .= "        {$eName} => {$returnClass}::class,\n";
        }
        $set             = rtrim($set);
        $override        = rtrim($override);
        $overrideFactory = rtrim($overrideFactory);

        $date = date('r');
        $meta = "<?php
/**
    The file has been generated automatically
    Date: {$date}
    DO NOT MODIFY THIS FILE MANUALLY, YOUR CHANGES WILL BE OVERWRITTEN!
**/
namespace PHPSTORM_META {

    registerArgumentsSet(
        'brokers',
{$set}
    );

    expectedArguments(\Zer0\AppTraits\Brokers::broker(), 0, argumentsSet('brokers'));
    expectedArguments(\Zer0\AppTraits\Brokers::factory(), 0, argumentsSet('brokers'));

    override(\Zer0\AppTraits\Brokers::broker(0), map([
        '' => '@',
{$override}
    ]));
    
    override(\Zer0\AppTraits\Brokers::factory(0), map([
        '' => '@',
{$overrideFactory}
    ]));
}
";
        file_put_contents($destfile, $meta);
        $this->cli->successLine("Written to $destfile in " . $this->elapsedMill() . " ms.");
    }

    public function dumpAction (): void
    {
        $this->phpStormMetaAction();
    }
}
