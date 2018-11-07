<?php

namespace Zer0\HTTP\Traits;

/**
 * Trait Files
 * @package Zer0\HTTP\Traits
 */
trait Files
{
    /**
     * @param string $file
     * @return bool
     */
    public function isUploadedFile(string $file): bool
    {
        return is_uploaded_file($file);
    }

    /**
     * @param string $file
     * @param string $dest
     * @return bool
     */
    public function moveUploadedFile(string $file, string $dest): bool
    {
        return move_uploaded_file($file, $dest);
    }
}
