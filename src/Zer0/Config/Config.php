<?php

namespace Zer0\Config;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Config
 * @package Zer0\Config
 */
class Config implements ConfigInterface
{
    /**
     * @var array
     */
    protected $path;

    /**
     * @var array
     */
    protected $sections = [];

    /**
     * @var string
     */
    protected $env;

    /**
     * @var App
     */
    protected $app;

    /**
     * @var array
     */
    protected $loadedFiles = [];

    /**
     * Config constructor.
     * @parm string $env
     * @param string $env
     * @param array|string $path
     * @param App $app
     */
    public function __construct(string $env, $path, App $app)
    {
        $this->path = (array)$path;
        $this->env = $env;
        $this->app = $app;
    }

    /**
     * @return array
     */
    public function getLoadedFiles()
    {
        return $this->loadedFiles;
    }

    /**
     * @param string $name
     * @return mixed|Section
     * @throws \Exception
     */
    public function __get(string $name)
    {
        $F = substr($name, 0, 1);
        if (strtoupper($F) === $F) {
            return $this->sections[$name] ?? ($this->sections[$name] = new Section(
                $this,
                $name,
                $this->env,
                    $this->loadedFiles
            ));
        }
        return $this->Main->{$name};
    }

    /**
     * @return array
     */
    public function sectionsList(): array
    {
        return array_unique(
            array_map('basename', array_map(
                'dirname',
                glob(
                    '{' . implode(',', $this->getPath()) . '}' . '/*/{*-,}{default,' . $this->env . '}.y{a,}ml',
                    GLOB_BRACE
                )
            ))
        );
    }

    /**
     * @return array
     */
    public function getPath(): array
    {
        return $this->path;
    }
}
