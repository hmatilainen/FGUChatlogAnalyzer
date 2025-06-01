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