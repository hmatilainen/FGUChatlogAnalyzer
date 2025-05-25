<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

class ChatlogService
{
    private $uploadDir;
    private $filesystem;

    public function __construct(string $projectDir)
    {
        $this->uploadDir = $projectDir . '/var/uploads';
        $this->filesystem = new Filesystem();
    }

    public function getUserDir(string $userId): string
    {
        return $this->uploadDir . '/' . $userId;
    }

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