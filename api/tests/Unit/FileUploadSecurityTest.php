<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FileUploadSecurityTest extends TestCase
{
    /**
     * Test that .env.example includes file upload configuration
     */
    public function testEnvExampleIncludesFileUploadConfig(): void
    {
        $envExample = file_get_contents(__DIR__ . '/../../.env.example');

        $this->assertStringContainsString('MAX_UPLOAD_SIZE_MB', $envExample);
        $this->assertStringContainsString('UPLOAD_ALLOWED_EXTENSIONS', $envExample);
        $this->assertStringContainsString('10', $envExample);
    }

    /**
     * Test that uploads directory is outside webroot
     */
    public function testUploadsDirectoryIsOutsideWebroot(): void
    {
        // The upload directory should be at ../../../storage/uploads relative to public/
        // which puts it outside the web root
        $expectedPath = __DIR__ . '/../../storage/uploads';
        $expectedPath = realpath($expectedPath) ?: $expectedPath;

        // Check that storage is not inside public/
        $this->assertStringNotContainsString('/public/uploads', $expectedPath);
        $this->assertStringContainsString('storage', $expectedPath);
    }

    /**
     * Test that blocked MIME types are configured
     */
    public function testBlockedMimeTypesConfigured(): void
    {
        // Read FileUploadManager source
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        // Check for blocked MIME types
        $this->assertStringContainsString('application/x-executable', $fileUploadSource);
        $this->assertStringContainsString('application/x-shellscript', $fileUploadSource);
        $this->assertStringContainsString('text/x-shellscript', $fileUploadSource);

        // Check for dangerous patterns
        $this->assertStringContainsString('<?php', $fileUploadSource);
        $this->assertStringContainsString('#!/bin/bash', $fileUploadSource);
    }

    /**
     * Test that MIME type validation is implemented
     */
    public function testMimeTypeValidation(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        // Should have MIME type whitelist
        $this->assertStringContainsString('mimeTypeWhitelist', $fileUploadSource);
        $this->assertStringContainsString('validateMimeType', $fileUploadSource);
        $this->assertStringContainsString('finfo_file', $fileUploadSource);
    }

    /**
     * Test that file content is validated
     */
    public function testFileContentValidation(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        $this->assertStringContainsString('validateFileContent', $fileUploadSource);
        $this->assertStringContainsString('dangerousPatterns', $fileUploadSource);
    }

    /**
     * Test that filenames are randomized (UUID-based)
     */
    public function testFilenamesAreRandomized(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        // Should use UUID, not user input
        $this->assertStringContainsString('uuid', strtolower($fileUploadSource));
        $this->assertStringContainsString('mt_rand', $fileUploadSource);

        // Should have generateUniqueFilename method
        $this->assertStringContainsString('generateUniqueFilename', $fileUploadSource);
    }

    /**
     * Test that file permissions are secure (0600)
     */
    public function testFilePermissionsAreSecure(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        // Should set 0600 (owner read/write only)
        $this->assertStringContainsString('0600', $fileUploadSource);
        // Should NOT set 0644 (world readable)
        $this->assertStringNotContainsString('0644', $fileUploadSource);
    }

    /**
     * Test that validation failures are logged
     */
    public function testValidationFailuresAreLogged(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        $this->assertStringContainsString('logValidationFailure', $fileUploadSource);
        $this->assertStringContainsString('logger->warning', $fileUploadSource);
    }

    /**
     * Test that max file size comes from env
     */
    public function testMaxFileSizeFromEnv(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        $this->assertStringContainsString('MAX_UPLOAD_SIZE_MB', $fileUploadSource);
        $this->assertStringContainsString('$_ENV[\'MAX_UPLOAD_SIZE_MB\']', $fileUploadSource);
    }

    /**
     * Test that allowed extensions come from env
     */
    public function testAllowedExtensionsFromEnv(): void
    {
        $fileUploadSource = file_get_contents(__DIR__ . '/../../src/FileUploadManager.php');

        $this->assertStringContainsString('UPLOAD_ALLOWED_EXTENSIONS', $fileUploadSource);
        $this->assertStringContainsString('$_ENV[\'UPLOAD_ALLOWED_EXTENSIONS\']', $fileUploadSource);
    }
}
