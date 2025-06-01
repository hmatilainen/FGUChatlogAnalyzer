<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Service for cleaning up user-uploaded files.
 *
 * @package App\Service
 */
class FileCleanupService
{
    private string $projectDir;
    private Filesystem $filesystem;

    /**
     * FileCleanupService constructor.
     *
     * @param string $projectDir
     */
    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->filesystem = new Filesystem();
    }

    /**
     * Remove all uploaded files for a user.
     *
     * @param string $username
     * @return void
     */
    public function cleanupUserFiles(string $username): void
    {
        $userDir = $this->projectDir . '/var/uploads/' . $username;
        if ($this->filesystem->exists($userDir)) {
            $this->filesystem->remove($userDir);
        }
    }
} 