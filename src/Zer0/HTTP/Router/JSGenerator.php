<?php

namespace Zer0\HTTP\Router;

use Zer0\HTTP\Exceptions\RouteNotFound;

/**
 * Class JSGenerator
 * @package Zer0\HTTP\Router
 */
class JSGenerator extends Basic
{
    /**
     * @return string
     */
    public function generate(): string
    {
        $routes = [];
        foreach ($this->routes as $routeName => $route) {
            if (!in_array('JS', $route['export'] ?? [], true)) {
                continue;
            }
            $routes[$routeName] = [
                'path' => $route['path_export'] ?? $route['path'],
                'defaults' => $route['defaults'] ?? [],
            ];
        }

        $cfg = "// The file has been generated automatically\n"
            . "// Date: " . date('r') . "\n"
            . "// DO NOT MODIFY THIS FILE MANUALLY, YOUR CHANGES WILL BE OVERWRITTEN!\n\n";

        $cfg .= 'module.exports = ' . json_encode($routes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . ';';

        return $cfg;
    }
}
