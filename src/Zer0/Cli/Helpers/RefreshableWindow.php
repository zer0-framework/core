<?php

namespace Zer0\Cli\Helpers;

use Hoa\Console\Cursor;

/**
 * Class RefreshableWindow
 * @package Zer0\Cli\Helpers
 */
class RefreshableWindow
{
    /**
     * @var int
     */
    protected $lines = 0;

    /**
     * @var int
     */
    protected $shownFrameLines = 0;

    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @var float
     */
    protected $lastRefresh;

    /**
     * @var float
     */
    protected $fps;

    /**
     *
     */
    public function __construct()
    {
        $this->initBuffer();
    }

    /**
     *
     */
    protected function initBuffer()
    {
        $this->started = true;
        ob_start(function (string $output) {
            $this->lines += substr_count($output, PHP_EOL);
            return $output;
        });
    }

    /**
     * @return RefreshableWindow
     */
    public function show(): self
    {
        ob_flush();
        $this->shownFrameLines = $this->lines;
        $this->lines = 0;
        return $this;
    }

    /**
     * @return RefreshableWindow
     */
    public function hide(): self
    {
        for (; $this->shownFrameLines > 0; --$this->shownFrameLines) {
            Cursor::clear('line');
            Cursor::move('up');
        }
        return $this;
    }


    /**
     *
     */
    public function getFps()
    {
        return $this->fps;
    }

    /**
     *
     */
    public function refresh()
    {
        if ($this->lastRefresh !== null) {
            $this->fps = 1 / (microtime(true) - $this->lastRefresh);
        }
        $this->lastRefresh = microtime(true);

        if ($this->started) {
            $contents = ob_get_contents();
            ob_end_clean();
            $this->started = false;
        } else {
            $contents = '';
        }
        $this->hide();
        echo $contents;
        $this->shownFrameLines = $this->lines;
        $this->lines = 0;
        $this->initBuffer();
    }

    /**
     *
     */
    public function destroy()
    {
        $this->hide();
        if ($this->started) {
            ob_end_flush();
            $this->started = false;
        }
    }
}
