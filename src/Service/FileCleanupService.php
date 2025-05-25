<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class FileCleanupService
{
    private string $projectDir;
    private Filesystem $filesystem;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->filesystem = new Filesystem();
    }

    public function cleanupUserFiles(string $username): void
    {
        $userDir = $this->projectDir . '/var/uploads/' . $username;
        if ($this->filesystem->exists($userDir)) {
            $this->filesystem->remove($userDir);
        }
    }
} 