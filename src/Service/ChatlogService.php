<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Service for managing chatlog file uploads and retrievals.
 *
 * @package App\Service
 */
class ChatlogService
{
    private $uploadDir;
    private $filesystem;

    /**
     * ChatlogService constructor.
     *
     * @param string $projectDir
     */
    public function __construct(string $projectDir)
    {
        $this->uploadDir = $projectDir . '/var/uploads';
        $this->filesystem = new Filesystem();
    }

    /**
     * Get the upload directory path.
     *
     * @return string
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * Get the user-specific upload directory path.
     *
     * @param string $userId
     * @return string
     */
    public function getUserDir(string $userId): string
    {
        return $this->uploadDir . '/' . $userId;
    }

    /**
     * Get the public upload directory path.
     *
     * @return string
     */
    public function getPublicDir(): string
    {
        return $this->uploadDir . '/public';
    }

    /**
     * Process an uploaded chatlog file and move it to the user's directory.
     *
     * @param UploadedFile $file
     * @param string $userId
     * @return array Result with success status and filename or error
     */
    public function processUpload(UploadedFile $file, string $userId): array
    {
        try {
            $userDir = $this->getUserDir($userId);
            if (!file_exists($userDir)) {
                $this->filesystem->mkdir($userDir, 0777);
            }

            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $file->move($userDir, $filename);

            return [
                'success' => true,
                'filename' => $filename
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to process upload: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Resolve a filename within a base directory, refusing to leave it.
     *
     * @param string $baseDir
     * @param string $filename
     * @return string|null Absolute resolved path, or null if the filename
     *                      would escape the base directory or doesn't exist.
     */
    public function resolveSafePath(string $baseDir, string $filename): ?string
    {
        $filename = basename($filename);
        $candidate = $baseDir . '/' . $filename;

        if (!file_exists($candidate)) {
            return null;
        }

        $realBase = realpath($baseDir);
        $realCandidate = realpath($candidate);

        if ($realBase === false || $realCandidate === false) {
            return null;
        }

        if (!str_starts_with($realCandidate, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realCandidate;
    }

    /**
     * Locate a chatlog file across the user's own directory, other users'
     * directories, and the public directory, in that priority order.
     *
     * @param string|null $userId
     * @param string $filename
     * @return array{path: string, isOwner: bool}|null
     */
    public function resolveFilepath(?string $userId, string $filename): ?array
    {
        if ($userId !== null) {
            $path = $this->resolveSafePath($this->getUserDir($userId), $filename);
            if ($path !== null) {
                return ['path' => $path, 'isOwner' => true];
            }
        }

        $uploadDir = $this->getUploadDir();
        if (is_dir($uploadDir)) {
            $dirs = array_diff(scandir($uploadDir), ['.', '..', 'public']);
            foreach ($dirs as $dir) {
                $path = $this->resolveSafePath($uploadDir . '/' . $dir, $filename);
                if ($path !== null) {
                    return ['path' => $path, 'isOwner' => false];
                }
            }
        }

        $path = $this->resolveSafePath($this->getPublicDir(), $filename);
        if ($path !== null) {
            return ['path' => $path, 'isOwner' => false];
        }

        return null;
    }

    /**
     * Get a list of chatlog files for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getUserChatlogs(string $userId): array
    {
        $userDir = $this->getUserDir($userId);
        if (!file_exists($userDir)) {
            return [];
        }

        $files = array_diff(scandir($userDir), ['.', '..']);
        $chatlogs = [];

        foreach ($files as $file) {
            $chatlogs[] = [
                'filename' => $file,
                'name' => $file,
                'size' => filesize($userDir . '/' . $file),
                'modified' => filemtime($userDir . '/' . $file)
            ];
        }

        return $chatlogs;
    }
} 