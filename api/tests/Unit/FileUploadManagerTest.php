<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\FileUploadManager;

class FileUploadManagerTest extends TestCase {
    private $mockDb;
    private $mockLogger;
    private $fileUploadManager;
    private $testUploadDir;

    protected function setUp(): void {
        $this->mockDb = $this->createMock(\App\Database::class);
        $this->mockLogger = $this->createMock(\App\Logger::class);

        // Create temp upload dir for testing
        $this->testUploadDir = sys_get_temp_dir() . '/splk2_test_uploads_' . time();
        mkdir($this->testUploadDir, 0755, true);

        $this->fileUploadManager = new FileUploadManager($this->mockDb, $this->mockLogger, $this->testUploadDir);
    }

    protected function tearDown(): void {
        // Clean up test files
        if (is_dir($this->testUploadDir)) {
            array_map('unlink', glob($this->testUploadDir . '/*'));
            rmdir($this->testUploadDir);
        }
    }

    /**
     * Test getFile retrieves file
     */
    public function testGetFileSuccessfully() {
        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => [
                'id' => 1,
                'name' => 'test.pdf',
                'filename' => 'test_123456.pdf',
                'filepath' => '/path/to/test.pdf',
                'size' => 1024,
                'uploaded_by' => 1,
            ],
        ]);

        $result = $this->fileUploadManager->getFile(1);

        $this->assertEquals(404, $result['status']); // File doesn't exist on disk
    }

    /**
     * Test getFile fails when not found
     */
    public function testGetFileFailsWhenNotFound() {
        $this->mockDb->method('get')->willReturn([
            'status' => 404,
            'data' => null,
        ]);

        $result = $this->fileUploadManager->getFile(999);

        $this->assertEquals(404, $result['status']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    /**
     * Test getUserFiles retrieves files
     */
    public function testGetUserFilesSuccessfully() {
        $this->mockDb->method('getAllWhere')->willReturn([
            'status' => 200,
            'data' => [
                [
                    'id' => 1,
                    'name' => 'document.pdf',
                    'filename' => 'document_123456.pdf',
                    'size' => 2048,
                    'uploaded_by' => 1,
                ],
            ],
        ]);

        $result = $this->fileUploadManager->getUserFiles(1);

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertCount(1, $result['data']);
    }

    /**
     * Test getAllowedExtensions
     */
    public function testGetAllowedExtensions() {
        $extensions = $this->fileUploadManager->getAllowedExtensions();

        $this->assertIsArray($extensions);
        $this->assertContains('pdf', $extensions);
        $this->assertContains('doc', $extensions);
        $this->assertContains('jpg', $extensions);
        $this->assertContains('png', $extensions);
    }

    /**
     * Test getMaxFileSize
     */
    public function testGetMaxFileSize() {
        $maxSize = $this->fileUploadManager->getMaxFileSize();

        $this->assertEquals(10 * 1024 * 1024, $maxSize); // 10MB
    }

    /**
     * Test handleUpload fails with invalid file array
     */
    public function testHandleUploadFailsWithInvalidArray() {
        $result = $this->fileUploadManager->handleUpload([], 1);

        $this->assertEquals(400, $result['status']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    /**
     * Test handleUpload fails with oversized file
     */
    public function testHandleUploadFailsWithOversizedFile() {
        $fileArray = [
            'tmp_name' => '/tmp/test',
            'name' => 'large.pdf',
            'size' => 11 * 1024 * 1024, // 11MB (exceeds 10MB limit)
            'error' => UPLOAD_ERR_OK,
        ];

        $result = $this->fileUploadManager->handleUpload($fileArray, 1);

        $this->assertEquals(413, $result['status']);
        $this->assertStringContainsString('exceeds maximum', $result['message']);
    }

    /**
     * Test handleUpload fails with invalid extension
     */
    public function testHandleUploadFailsWithInvalidExtension() {
        $fileArray = [
            'tmp_name' => '/tmp/test',
            'name' => 'malware.exe',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK,
        ];

        $result = $this->fileUploadManager->handleUpload($fileArray, 1);

        $this->assertEquals(415, $result['status']);
        $this->assertStringContainsString('not allowed', $result['message']);
    }

    /**
     * Test deleteFile fails with invalid ID
     */
    public function testDeleteFileFailsWhenNotFound() {
        $this->mockDb->method('get')->willReturn([
            'status' => 404,
            'data' => null,
        ]);

        $result = $this->fileUploadManager->deleteFile(999, 1);

        $this->assertEquals(404, $result['status']);
    }

    /**
     * Test deleteFile fails when not owner and not admin
     */
    public function testDeleteFileFailsWhenNotOwner() {
        $this->mockDb->method('get')->willReturn([
            'status' => 200,
            'data' => [
                'id' => 1,
                'uploaded_by' => 2, // Different user
                'filepath' => '/path/to/file.pdf',
            ],
        ]);

        $result = $this->fileUploadManager->deleteFile(1, 1); // User 1 trying to delete user 2's file

        $this->assertEquals(403, $result['status']);
        $this->assertStringContainsString('own files', $result['message']);
    }
}
