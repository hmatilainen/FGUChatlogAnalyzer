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

    /**
     * Build a ChatlogService whose upload dir is a fresh temp directory, so
     * tests can freely create user/public subdirectories without touching
     * the real var/uploads tree.
     *
     * @return array{0: ChatlogService, 1: string} [$service, $uploadDir]
     */
    private function makeServiceWithUploadDir(): array
    {
        $projectDir = sys_get_temp_dir() . '/chatlog_test_project_' . uniqid();
        mkdir($projectDir . '/var/uploads', 0777, true);

        return [new ChatlogService($projectDir), $projectDir . '/var/uploads'];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testResolveFilepathFindsFileInOwnDir()
    {
        [$service, $uploadDir] = $this->makeServiceWithUploadDir();
        mkdir($uploadDir . '/user1');
        file_put_contents($uploadDir . '/user1/chatlog.html', 'contents');

        $resolved = $service->resolveFilepath('user1', 'chatlog.html');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved['isOwner']);
        $this->assertEquals(realpath($uploadDir . '/user1/chatlog.html'), $resolved['path']);

        $this->removeDir(dirname($uploadDir));
    }

    public function testResolveFilepathFindsFileInAnotherUsersDir()
    {
        [$service, $uploadDir] = $this->makeServiceWithUploadDir();
        mkdir($uploadDir . '/user1');
        mkdir($uploadDir . '/user2');
        file_put_contents($uploadDir . '/user2/chatlog.html', 'contents');

        $resolved = $service->resolveFilepath('user1', 'chatlog.html');

        $this->assertNotNull($resolved);
        $this->assertFalse($resolved['isOwner']);
        $this->assertEquals(realpath($uploadDir . '/user2/chatlog.html'), $resolved['path']);

        $this->removeDir(dirname($uploadDir));
    }

    public function testResolveFilepathFindsFileInPublicDir()
    {
        [$service, $uploadDir] = $this->makeServiceWithUploadDir();
        mkdir($uploadDir . '/user1');
        mkdir($uploadDir . '/public');
        file_put_contents($uploadDir . '/public/chatlog.html', 'contents');

        $resolved = $service->resolveFilepath('user1', 'chatlog.html');

        $this->assertNotNull($resolved);
        $this->assertFalse($resolved['isOwner']);
        $this->assertEquals(realpath($uploadDir . '/public/chatlog.html'), $resolved['path']);

        $this->removeDir(dirname($uploadDir));
    }

    public function testResolveFilepathReturnsNullWhenNotFoundAnywhere()
    {
        [$service, $uploadDir] = $this->makeServiceWithUploadDir();
        mkdir($uploadDir . '/user1');

        $resolved = $service->resolveFilepath('user1', 'chatlog.html');

        $this->assertNull($resolved);

        $this->removeDir(dirname($uploadDir));
    }

    public function testResolveFilepathSkipsOwnDirScanWhenUserIdIsNull()
    {
        [$service, $uploadDir] = $this->makeServiceWithUploadDir();
        mkdir($uploadDir . '/user1');
        file_put_contents($uploadDir . '/user1/chatlog.html', 'contents');

        $resolved = $service->resolveFilepath(null, 'chatlog.html');

        $this->assertNotNull($resolved);
        $this->assertFalse($resolved['isOwner']);
        $this->assertEquals(realpath($uploadDir . '/user1/chatlog.html'), $resolved['path']);

        $this->removeDir(dirname($uploadDir));
    }
}
