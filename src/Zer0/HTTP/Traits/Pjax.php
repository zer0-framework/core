<?php

namespace Zer0\HTTP\Traits;

/**
 * Trait Pjax
 * @package Zer0\HTTP\Traits
 */
trait Pjax
{
    /**
     * @return bool
     */
    public function isPjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_PJAX']);
    }

    /**
     * @param string $version
     */
    public function setPjaxVersion(string $version): void
    {
        $this->header('X-PJAX-Version: ' . $version);
    }

    /**
     * @return bool
     */
    public function isAjaxRequest(): bool
    {
        return !isset($_SERVER['HTTP_X_PJAX']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
