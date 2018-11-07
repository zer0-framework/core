<?php

namespace Zer0\Config;

use Zer0\Config\Exceptions\BadFile;
use Zer0\Config\Exceptions\UnableToReadConfigFile;
use Zer0\Config\Interfaces\ConfigInterface;

/**
 * Class Section
 * @package Zer0\Config
 */
class Section implements ConfigInterface
{

    /**
     * @var ConfigInterface
     */
    protected $parent;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $sections = [];

    /**
     * @var string
     */
    protected $env;

    protected $loadedFiles;

    /**
     * Section constructor.
     * @param ConfigInterface $parent
     * @param string $name
     * @param string $env
     * @param array &$loadedFiles
     * @throws UnableToReadConfigFile
     */
    public function __construct(ConfigInterface $parent, string $name, string $env, &$loadedFiles)
    {
        $this->parent = $parent;
        $this->name = $name;
        $this->env = $env;
        $this->loadedFiles =& $loadedFiles;

        $combined = [];

        $pattern = '{' . implode(',', $this->getPath()) . '}' . '/{*-,}{default,' . $this->env . '}.y{a,}ml';
        $files = glob($pattern, GLOB_BRACE);
        foreach ($files as $file) {
            if ($loadedFiles !== null) {
                $loadedFiles[] = $file;
            }
            $data = \yaml_parse_file($file, 0, $ndocs, [
                '!env' => function ($value, string $tag, int $flags) {
                    return getenv($value);
                }
            ]);
            if ($data === false) {
                throw new UnableToReadConfigFile(substr($file, strlen(ZERO_ROOT)));
            }
            if (is_array($data)) {
                $combined = array_merge($combined, $data);
            }
        }

        $this->data = $combined;
    }

    /**
     * @return array
     */
    public function getPath(): array
    {
        $path = $this->parent->getPath();
        foreach ($path as &$item) {
            $item .= '/' . $this->getName();
        }
        return $path;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
     * @return int|bool
     */
    public function lastModified()
    {
        return filemtime($this->path);
    }

    /**
     * @param string $name
     * @return mixed|null|Section
     * @throws UnableToReadConfigFile
     */
    public function __get(string $name)
    {
        $F = substr($name, 0, 1);
        if (ctype_alpha($F) && strtoupper($F) === $F) {
            return $this->sections[$name] ?? ($this->sections[$name] = new self(
                    $this,
                    $name,
                    $this->env,
                    $this->loadedFiles
                ));
        }
        return $this->data[$name] ?? null;
    }

    /**
     * @param string $name
     * @param $value
     */
    public function __set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getValue(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     *
     */
    public function toArray()
    {
        return $this->data;
    }
}
