<?php

namespace Zer0\HTTP\Router;

use Zer0\HTTP\Exceptions\MethodNotAllowed;

/**
 * Class Basic
 * @package Zer0\HTTP\Router
 */
class Basic
{

    /**
     * @var array
     */
    protected $routes;

    /**
     * @var array
     */
    protected $stringMap;

    /**
     * @var array
     */
    protected $patternMap;

    /**
     * @var array
     */
    protected $methodsMap;

    /**
     * @param $routes
     * @throws \Exception
     */
    public function __construct($routes)
    {
        $this->routes = $routes;

        $i = 1;
        foreach ($this->routes as &$conf) {
            if (!isset($conf['sort'])) {
                $conf['sort'] = $i;
            }
            ++$i;
        }

        uasort($this->routes, function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });


        $urlPrefix = '';
        $this->stringMap = [];
        $this->patternMap = [];
        $this->methodsMap = [];

        foreach ($this->routes as $name => &$conf) {
            if ($conf['type'] ?? '' === 'websocket') {
                continue;
            }
            if (!array_key_exists('action', $conf['defaults'] ?? [])) {
                $conf['defaults']['action'] = 'index';
            }

            // Parse {param} placeholders
            $params = [];
            $regex = '^' . preg_quote($urlPrefix)
                . preg_replace_callback('~/\{(.*?)\}|[^\{\/]+~', function ($match) use (&$params, $conf) {
                    if (!isset($match[1])) { // A part of the URL
                        return preg_quote($match[0]);
                    }
                    $paramName = $match[1];
                    $params[] = $paramName;
                    $format = $conf['format'][$paramName] ?? '[^/]+';
                    if (isset($conf['defaults'][$paramName])) {
                        return '(?:/(' . $format . ')|/?$)';
                    } else {
                        return '/(' . $format . ')';
                    }
                }, $conf['path']) . '$';
            if (!count($params)) {
                // If there aren't any placeholders
                $key = $urlPrefix . $conf['path'];
                if (isset($this->stringMap[$key])) {
                    throw new \Exception('Route \'' . $name . '\' has the same path definition as route \'' . $this->stringMap[$key] . '\'');
                }
                $this->stringMap[$key] = $name;
            } else { // If there are
                $this->patternMap[$name] = [$regex, $params];
            }

            if ($conf['methods'] ?? []) {
                $this->methodsMap[$name] = array_map('strtoupper', $conf['methods']);
            }
        }
    }

    /**
     * @throws MethodNotAllowed
     * @return void
     */
    public function execute(): void
    {
        $uri = $_SERVER['DOCUMENT_URI'] ?? $_SERVER['SCRIPT_NAME'];
        $routeName = $this->stringMap[$uri] ?? null;
        if ($routeName === null) {
            foreach ($this->patternMap as $name => $item) {
                list($regex, $params) = $item;
                if (preg_match('~' . $regex . '~', $uri, $match)) {
                    $routeName = $name;
                    for ($i = 1, $n = count($match); $i < $n; ++$i) {
                        $_SERVER['ROUTE_' . strtoupper($params[$i - 1])] = $match[$i];
                    }
                }
            }
        }

        if ($routeName !== null) {
            $_SERVER['ROUTE'] = $routeName;
            $route = $this->routes[$routeName];
            if (isset($route['defaults'])) {
                foreach ($route['defaults'] as $key => $value) {
                    $key = 'ROUTE_' . strtoupper($key);
                    $_SERVER[$key] = $_SERVER[$key] ?? $value;
                }
            }
            if (isset($route['methods'])) {
                if (!in_array($_SERVER['REQUEST_METHOD'], $this->methodsMap[$routeName])) {
                    throw new MethodNotAllowed;
                }
            }
        }
    }
}
