<?php
namespace App;

class FileUploadManager {
    private $db;
    private $logger;
    private $uploadDir;
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    private $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'csv'];

    public function __construct(Database $db, Logger $logger, string $uploadDir = null) {
        $this->db = $db;
        $this->logger = $logger;
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../public/uploads';

        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Handle file upload
     *
     * @param array $fileArray $_FILES entry
     * @param int $userId User uploading the file
     * @param string $fileName Optional display name
     * @return array
     */
    public function handleUpload(array $fileArray, int $userId, string $fileName = null): array {
        try {
            // Validate file was uploaded
            if (!isset($fileArray['tmp_name']) || !isset($fileArray['name']) || !isset($fileArray['size'])) {
                return [
                    'status' => 400,
                    'message' => 'Invalid file upload',
                    'data' => null,
                ];
            }

            // Validate file size
            if ($fileArray['size'] > $this->maxFileSize) {
                return [
                    'status' => 413,
                    'message' => "File size exceeds maximum of " . ($this->maxFileSize / 1024 / 1024) . "MB",
                    'data' => null,
                ];
            }

            // Validate file extension
            $extension = $this->getFileExtension($fileArray['name']);
            if (!in_array(strtolower($extension), $this->allowedExtensions)) {
                return [
                    'status' => 415,
                    'message' => "File type not allowed. Allowed types: " . implode(', ', $this->allowedExtensions),
                    'data' => null,
                ];
            }

            // Check for upload errors
            if ($fileArray['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = $this->getUploadErrorMessage($fileArray['error']);
                return [
                    'status' => 400,
                    'message' => "Upload error: " . $errorMessage,
                    'data' => null,
                ];
            }

            // Generate unique filename
            $uniqueFilename = $this->generateUniqueFilename($fileArray['name']);
            $filepath = $this->uploadDir . '/' . $uniqueFilename;

            // Move uploaded file
            if (!move_uploaded_file($fileArray['tmp_name'], $filepath)) {
                $this->logger->error('Failed to move uploaded file', [
                    'user_id' => $userId,
                    'original_name' => $fileArray['name'],
                    'tmp_path' => $fileArray['tmp_name'],
                    'target_path' => $filepath,
                ]);

                return [
                    'status' => 500,
                    'message' => 'Failed to save file',
                    'data' => null,
                ];
            }

            // Set file permissions
            chmod($filepath, 0644);

            // Store in database
            $displayName = $fileName ?? $fileArray['name'];
            $this->db->execute(
                'INSERT INTO files (name, filename, filepath, size, uploaded_by) VALUES (?, ?, ?, ?, ?)',
                [$displayName, $uniqueFilename, $filepath, $fileArray['size'], $userId]
            );

            $fileId = $this->db->lastInsertId();

            $this->logger->info('File uploaded successfully', [
                'file_id' => $fileId,
                'user_id' => $userId,
                'filename' => $uniqueFilename,
                'size' => $fileArray['size'],
            ]);

            return [
                'status' => 200,
                'message' => 'File uploaded successfully',
                'data' => [
                    'file_id' => $fileId,
                    'name' => $displayName,
                    'filename' => $uniqueFilename,
                    'size' => $fileArray['size'],
                    'upload_url' => "/files/{$fileId}/download",
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error uploading file', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error uploading file',
                'data' => null,
            ];
        }
    }

    /**
     * Get file for download
     *
     * @param int $fileId
     * @return array
     */
    public function getFile(int $fileId): array {
        try {
            $result = $this->db->get('files', $fileId);

            if ($result['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'File not found',
                    'data' => null,
                ];
            }

            $file = $result['data'];

            // Check if file exists on disk
            if (!file_exists($file['filepath'])) {
                $this->logger->warning('File not found on disk', [
                    'file_id' => $fileId,
                    'filepath' => $file['filepath'],
                ]);

                return [
                    'status' => 404,
                    'message' => 'File not found on disk',
                    'data' => null,
                ];
            }

            return [
                'status' => 200,
                'message' => 'File retrieved',
                'data' => $file,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving file', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error retrieving file',
                'data' => null,
            ];
        }
    }

    /**
     * Delete file
     *
     * @param int $fileId
     * @param int $userId User requesting deletion
     * @return array
     */
    public function deleteFile(int $fileId, int $userId): array {
        try {
            $result = $this->db->get('files', $fileId);

            if ($result['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'File not found',
                    'data' => null,
                ];
            }

            $file = $result['data'];

            // Check if user is admin or file owner
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);
            $isAdmin = $rbac->hasRole((object)['id' => $userId], 'admin');
            $isOwner = $file['uploaded_by'] == $userId;

            if (!$isAdmin && !$isOwner) {
                return [
                    'status' => 403,
                    'message' => 'You can only delete your own files',
                    'data' => null,
                ];
            }

            // Delete from disk
            if (file_exists($file['filepath'])) {
                if (!unlink($file['filepath'])) {
                    $this->logger->warning('Failed to delete file from disk', [
                        'file_id' => $fileId,
                        'filepath' => $file['filepath'],
                    ]);
                }
            }

            // Delete from database
            $this->db->execute('DELETE FROM files WHERE id = ?', [$fileId]);

            $this->logger->info('File deleted', [
                'file_id' => $fileId,
                'deleted_by' => $userId,
                'filename' => $file['filename'],
            ]);

            return [
                'status' => 200,
                'message' => 'File deleted successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error deleting file', [
                'file_id' => $fileId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error deleting file',
                'data' => null,
            ];
        }
    }

    /**
     * Get all files uploaded by a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserFiles(int $userId): array {
        try {
            $result = $this->db->getAllWhere('files', 'uploaded_by = ?', [$userId]);

            if ($result['status'] !== 200) {
                return [
                    'status' => 200,
                    'message' => 'No files found',
                    'data' => [],
                ];
            }

            return [
                'status' => 200,
                'message' => 'Files retrieved',
                'data' => $result['data'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving user files', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error retrieving files',
                'data' => null,
            ];
        }
    }

    /**
     * Get file extension from filename
     *
     * @param string $filename
     * @return string
     */
    private function getFileExtension(string $filename): string {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Generate unique filename to avoid collisions
     *
     * @param string $originalFilename
     * @return string
     */
    private function generateUniqueFilename(string $originalFilename): string {
        $extension = $this->getFileExtension($originalFilename);
        $name = pathinfo($originalFilename, PATHINFO_FILENAME);

        // Sanitize name - keep only alphanumeric, dash, underscore
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);

        // Add timestamp and random string
        $unique = time() . '_' . bin2hex(random_bytes(4));

        return "{$name}_{$unique}.{$extension}";
    }

    /**
     * Get user-friendly upload error message
     *
     * @param int $errorCode
     * @return string
     */
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

    /**
     * Get allowed file extensions
     *
     * @return array
     */
    public function getAllowedExtensions(): array {
        return $this->allowedExtensions;
    }

    /**
     * Get max file size in bytes
     *
     * @return int
     */
    public function getMaxFileSize(): int {
        return $this->maxFileSize;
    }
}
