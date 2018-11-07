<?php

namespace Zer0\Drivers\Traits;

/**
 * Trait QueryLog
 * @package Zer0\Drivers\Traits
 */
trait QueryLog
{
    /**
     * @var array
     */
    protected $queryLog = [];

    /**
     * @var bool
     */
    protected $queryLogging = false;

    /**
     * @return array
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     *
     */
    public function resetQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * @param bool $enabled
     */
    public function enableQueryLogging(bool $enabled = true): void
    {
        $this->queryLogging = $enabled;
    }
}
