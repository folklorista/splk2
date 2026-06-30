<?php
namespace App;

class FileUploadManager {
    private $db;
    private $logger;
    private $uploadDir;
    private $maxFileSize;
    private $allowedExtensions;
    private $mimeTypeWhitelist;
    private $blockedMimeTypes;
    private $dangerousPatterns;

    public function __construct(Database $db, Logger $logger, string $uploadDir = null) {
        $this->db = $db;
        $this->logger = $logger;
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../../storage/uploads';

        $this->maxFileSize = (int)($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 10) * 1024 * 1024;

        $envExtensions = $_ENV['UPLOAD_ALLOWED_EXTENSIONS'] ?? 'pdf,doc,docx,xls,xlsx,txt,png,jpg,jpeg,gif,csv';
        $this->allowedExtensions = array_map('trim', explode(',', $envExtensions));

        $this->mimeTypeWhitelist = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
        ];

        $this->blockedMimeTypes = [
            'application/x-executable',
            'application/x-elf',
            'application/x-mach-binary',
            'application/x-msdownload',
            'application/x-msdos-program',
            'application/x-shellscript',
            'text/x-shellscript',
            'application/x-perl',
            'text/x-perl',
            'application/x-python',
            'text/x-python',
        ];

        $this->dangerousPatterns = [
            'php' => ['<?php', '<?' , '<%'],
            'script' => ['#!/bin/bash', '#!/bin/sh', '#!/usr/bin/perl'],
            'html' => ['<!DOCTYPE', '<html', '<script'],
        ];

        $this->ensureUploadDirectory();
    }

    private function ensureUploadDirectory(): void {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0700, true);
        }
        if (!is_writable($this->uploadDir)) {
            chmod($this->uploadDir, 0700);
        }
    }

    public function handleUpload(array $fileArray, int $userId, string $fileName = null): array {
        try {
            if (!isset($fileArray['tmp_name']) || !isset($fileArray['name']) || !isset($fileArray['size'])) {
                $this->logValidationFailure($userId, $fileArray['name'] ?? 'unknown', 'Missing file information');
                return ['status' => 400, 'message' => 'Invalid file upload', 'data' => null];
            }

            if ($fileArray['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = $this->getUploadErrorMessage($fileArray['error']);
                $this->logValidationFailure($userId, $fileArray['name'], $errorMessage);
                return ['status' => 400, 'message' => "Upload error: $errorMessage", 'data' => null];
            }

            if ($fileArray['size'] > $this->maxFileSize) {
                $maxMB = $this->maxFileSize / 1024 / 1024;
                $this->logValidationFailure($userId, $fileArray['name'], "File exceeds size limit ($maxMB MB)");
                return ['status' => 413, 'message' => "File size exceeds maximum of {$maxMB}MB", 'data' => null];
            }

            if ($fileArray['size'] === 0) {
                $this->logValidationFailure($userId, $fileArray['name'], 'Empty file');
                return ['status' => 400, 'message' => 'Empty files are not allowed', 'data' => null];
            }

            $extension = $this->getFileExtension($fileArray['name']);

            if (!in_array(strtolower($extension), $this->allowedExtensions)) {
                $this->logValidationFailure($userId, $fileArray['name'], "Extension not allowed: $extension");
                return ['status' => 415, 'message' => 'File type not allowed', 'data' => null];
            }

            $mimeValidation = $this->validateMimeType($fileArray['tmp_name'], $extension);
            if (!$mimeValidation['valid']) {
                $this->logValidationFailure($userId, $fileArray['name'], "MIME type mismatch: {$mimeValidation['reason']}");
                return ['status' => 415, 'message' => 'Invalid file type detected', 'data' => null];
            }

            $contentValidation = $this->validateFileContent($fileArray['tmp_name'], $extension);
            if (!$contentValidation['valid']) {
                $this->logValidationFailure($userId, $fileArray['name'], "Dangerous content detected: {$contentValidation['reason']}");
                return ['status' => 415, 'message' => 'File contains disallowed content', 'data' => null];
            }

            if ($this->isExecutableContent($fileArray['tmp_name'])) {
                $this->logValidationFailure($userId, $fileArray['name'], 'Executable content detected');
                return ['status' => 415, 'message' => 'Executable files are not allowed', 'data' => null];
            }

            $uniqueFilename = $this->generateUniqueFilename($extension);
            $filepath = $this->uploadDir . '/' . $uniqueFilename;

            if (!move_uploaded_file($fileArray['tmp_name'], $filepath)) {
                $this->logger->error('Failed to move uploaded file', [
                    'user_id' => $userId,
                    'original_name' => $fileArray['name'],
                    'target_path' => $filepath,
                ]);
                return ['status' => 500, 'message' => 'Failed to save file', 'data' => null];
            }

            chmod($filepath, 0600);

            $displayName = $fileName ?? pathinfo($fileArray['name'], PATHINFO_FILENAME);
            $this->db->execute(
                'INSERT INTO files (name, filename, filepath, size, mime_type, uploaded_by) VALUES (:name, :filename, :filepath, :size, :mime_type, :user_id)',
                [
                    ':name' => $displayName,
                    ':filename' => $uniqueFilename,
                    ':filepath' => $filepath,
                    ':size' => $fileArray['size'],
                    ':mime_type' => $mimeValidation['mime'],
                    ':user_id' => $userId,
                ]
            );

            $fileId = $this->db->lastInsertId();

            $this->logger->info('File uploaded successfully', [
                'file_id' => $fileId,
                'user_id' => $userId,
                'filename' => $uniqueFilename,
                'size' => $fileArray['size'],
                'mime_type' => $mimeValidation['mime'],
            ]);

            return [
                'status' => 201,
                'message' => 'File uploaded successfully',
                'data' => [
                    'file_id' => $fileId,
                    'name' => $displayName,
                    'filename' => $uniqueFilename,
                    'size' => $fileArray['size'],
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error uploading file', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return ['status' => 500, 'message' => 'Error uploading file', 'data' => null];
        }
    }

    private function validateMimeType(string $tmpPath, string $extension): array {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if ($detectedMime === false) {
            return ['valid' => false, 'mime' => null, 'reason' => 'Could not determine MIME type'];
        }

        if (in_array($detectedMime, $this->blockedMimeTypes)) {
            return ['valid' => false, 'mime' => $detectedMime, 'reason' => 'Blocked MIME type'];
        }

        $expectedMime = $this->mimeTypeWhitelist[$extension] ?? null;
        if ($expectedMime && $detectedMime !== $expectedMime) {
            return ['valid' => false, 'mime' => $detectedMime, 'reason' => "MIME mismatch: expected $expectedMime, got $detectedMime"];
        }

        return ['valid' => true, 'mime' => $detectedMime];
    }

    private function validateFileContent(string $tmpPath, string $extension): array {
        $content = file_get_contents($tmpPath, false, null, 0, 4096);
        if ($content === false) {
            return ['valid' => false, 'reason' => 'Could not read file'];
        }

        foreach ($this->dangerousPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    return ['valid' => false, 'reason' => "Dangerous pattern detected ($type)"];
                }
            }
        }

        return ['valid' => true];
    }

    private function isExecutableContent(string $tmpPath): bool {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $executableMimes = [
            'application/x-executable',
            'application/x-elf',
            'application/x-mach-binary',
            'application/x-msdownload',
            'application/x-dosexec',
            'application/x-shellscript',
            'text/x-shellscript',
        ];

        return in_array($mime, $executableMimes);
    }

    private function generateUniqueFilename(string $extension): string {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        return $uuid . '.' . strtolower($extension);
    }

    private function logValidationFailure(int $userId, string $filename, string $reason): void {
        $this->logger->warning('File upload validation failed', [
            'user_id' => $userId,
            'filename' => $filename,
            'reason' => $reason,
        ]);
    }

    private function getFileExtension(string $filename): string {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function getUploadErrorMessage(int $errorCode): string {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error',
        };
    }

    public function getFile(int $fileId): array {
        try {
            $result = $this->db->get('files', $fileId);
            if ($result['status'] !== 200) {
                return ['status' => 404, 'message' => 'File not found', 'data' => null];
            }

            $file = $result['data'];
            if (!file_exists($file['filepath'])) {
                $this->logger->warning('File not found on disk', ['file_id' => $fileId, 'filepath' => $file['filepath']]);
                return ['status' => 404, 'message' => 'File not found on disk', 'data' => null];
            }

            return ['status' => 200, 'message' => 'File retrieved', 'data' => $file];
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving file', ['file_id' => $fileId, 'error' => $e->getMessage()]);
            return ['status' => 500, 'message' => 'Error retrieving file', 'data' => null];
        }
    }

    public function deleteFile(int $fileId, int $userId): array {
        try {
            $result = $this->db->get('files', $fileId);
            if ($result['status'] !== 200) {
                return ['status' => 404, 'message' => 'File not found', 'data' => null];
            }

            $file = $result['data'];
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);
            $isAdmin = $rbac->hasRole((object)['id' => $userId], 'admin');
            $isOwner = $file['uploaded_by'] == $userId;

            if (!$isAdmin && !$isOwner) {
                return ['status' => 403, 'message' => 'You can only delete your own files', 'data' => null];
            }

            if (file_exists($file['filepath']) && !unlink($file['filepath'])) {
                $this->logger->warning('Failed to delete file from disk', ['file_id' => $fileId]);
            }

            $this->db->execute('DELETE FROM files WHERE id = :id', [':id' => $fileId]);
            $this->logger->info('File deleted', ['file_id' => $fileId, 'deleted_by' => $userId]);

            return ['status' => 200, 'message' => 'File deleted successfully', 'data' => null];
        } catch (\Exception $e) {
            $this->logger->error('Error deleting file', ['file_id' => $fileId, 'error' => $e->getMessage()]);
            return ['status' => 500, 'message' => 'Error deleting file', 'data' => null];
        }
    }

    public function getUserFiles(int $userId): array {
        try {
            $result = $this->db->getAllWhere('files', 'uploaded_by = :user_id', [':user_id' => $userId]);
            if ($result['status'] !== 200) {
                return ['status' => 200, 'message' => 'No files found', 'data' => []];
            }
            return ['status' => 200, 'message' => 'Files retrieved', 'data' => $result['data']];
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving user files', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return ['status' => 500, 'message' => 'Error retrieving files', 'data' => null];
        }
    }

    public function getAllowedExtensions(): array {
        return $this->allowedExtensions;
    }

    public function getMaxFileSize(): int {
        return $this->maxFileSize;
    }
}
