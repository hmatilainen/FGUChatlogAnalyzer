<?php

namespace App\Service;

/**
 * Service for retrieving Git commit hash and date for the current project.
 *
 * @package App\Service
 */
class GitInfoService
{
    private ?string $gitHash = null;
    private ?string $gitDate = null;

    /**
     * GitInfoService constructor. Loads git info on instantiation.
     */
    public function __construct()
    {
        $this->loadGitInfo();
    }

    /**
     * Get the short Git commit hash.
     *
     * @return string|null
     */
    public function getGitHash(): ?string
    {
        return $this->gitHash ? substr($this->gitHash, 0, 7) : null;
    }

    /**
     * Get the date of the current Git commit.
     *
     * @return string|null
     */
    public function getGitDate(): ?string
    {
        return $this->gitDate;
    }

    /**
     * Load the Git commit hash and date from the .git directory.
     *
     * @return void
     */
    private function loadGitInfo(): void
    {
        $gitPath = dirname(__DIR__, 2) . '/.git';
        
        if (is_dir($gitPath)) {
            // Get the current HEAD hash
            $headFile = $gitPath . '/HEAD';
            if (file_exists($headFile)) {
                $head = trim(file_get_contents($headFile));
                if (strpos($head, 'ref: ') === 0) {
                    $ref = substr($head, 5);
                    $refFile = $gitPath . '/' . $ref;
                    if (file_exists($refFile)) {
                        $this->gitHash = trim(file_get_contents($refFile));
                    }
                } else {
                    $this->gitHash = $head;
                }
            }

            // Get the commit date
            if ($this->gitHash) {
                $logFile = $gitPath . '/logs/HEAD';
                if (file_exists($logFile)) {
                    $logs = file($logFile);
                    if (!empty($logs)) {
                        $lastLog = end($logs);
                        // Git log format: <old-hash> <new-hash> <author> <timestamp> <timezone> <message>
                        $parts = explode(' ', $lastLog);
                        if (count($parts) >= 4) {
                            $timestamp = (int)$parts[3];
                            if ($timestamp > 0) {
                                $this->gitDate = date('Y-m-d H:i:s', $timestamp);
                            }
                        }
                    }
                }
            }
        }
    }
} 