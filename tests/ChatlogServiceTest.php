<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Service\ChatlogService;

class ChatlogServiceTest extends TestCase
{
    private function makeService(): ChatlogService
    {
        // ChatlogService only needs $projectDir for its constructor; the
        // resolveSafePath() method under test does not depend on it.
        return new ChatlogService(sys_get_temp_dir());
    }

    public function testResolvesFileInsideBaseDir()
    {
        $baseDir = sys_get_temp_dir() . '/chatlog_test_base_' . uniqid();
        mkdir($baseDir);
        $filePath = $baseDir . '/chatlog.html';
        file_put_contents($filePath, 'contents');

        $service = $this->makeService();
        $resolved = $service->resolveSafePath($baseDir, 'chatlog.html');

        $this->assertNotNull($resolved);
        $this->assertEquals(realpath($filePath), $resolved);

        unlink($filePath);
        rmdir($baseDir);
    }

    public function testDoesNotResolveParentDirectoryTraversal()
    {
        $root = sys_get_temp_dir() . '/chatlog_test_root_' . uniqid();
        mkdir($root);
        $baseDir = $root . '/base';
        mkdir($baseDir);
        $escapeTarget = $root . '/secret.txt';
        file_put_contents($escapeTarget, 'top secret');

        $service = $this->makeService();
        $resolved = $service->resolveSafePath($baseDir, '../secret.txt');

        $this->assertNull($resolved);

        unlink($escapeTarget);
        rmdir($baseDir);
        rmdir($root);
    }

    public function testDoesNotResolveDeepTraversal()
    {
        $baseDir = sys_get_temp_dir() . '/chatlog_test_base_' . uniqid();
        mkdir($baseDir);

        $service = $this->makeService();
        $resolved = $service->resolveSafePath($baseDir, '../../etc/passwd');

        $this->assertNull($resolved);

        rmdir($baseDir);
    }

    public function testReturnsNullForNonexistentFile()
    {
        $baseDir = sys_get_temp_dir() . '/chatlog_test_base_' . uniqid();
        mkdir($baseDir);

        $service = $this->makeService();
        $resolved = $service->resolveSafePath($baseDir, 'does_not_exist.html');

        $this->assertNull($resolved);

        rmdir($baseDir);
    }
}
