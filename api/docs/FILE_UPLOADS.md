# File Upload System

## Overview

The file upload system allows authenticated users to upload, manage, and download files. Files are stored on disk with metadata tracked in the database for audit and recovery.

## Use Cases

- Attach documents to records
- Upload user avatars/profiles
- Store inventory item photos/diagrams
- Backup important documents
- Manage file attachments for items/users/groups

## Upload Constraints

| Constraint | Value |
|-----------|-------|
| **Max File Size** | 10 MB |
| **Allowed Extensions** | pdf, doc, docx, xls, xlsx, txt, png, jpg, jpeg, gif, zip, csv |
| **Storage Location** | `/public/uploads/` |
| **File Naming** | Auto-sanitized, timestamp + random suffix to prevent collisions |

## API Endpoints

### Upload File

```http
POST /files/upload
Content-Type: multipart/form-data
Authorization: Bearer <token>

file=@document.pdf&name=My%20Document
```

**Form Parameters**:
- `file` (required) - File to upload
- `name` (optional) - Display name for the file

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "File uploaded successfully",
  "data": {
    "file_id": 42,
    "name": "My Document",
    "filename": "my_document_1719669637_a1b2c3d4.pdf",
    "size": 102400,
    "upload_url": "/files/42/download"
  }
}
```

**Using curl**:
```bash
curl -X POST http://localhost:8000/files/upload \
  -H "Authorization: Bearer <token>" \
  -F "file=@document.pdf" \
  -F "name=My Important Document"
```

**Using JavaScript/Fetch**:
```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('name', 'My Document');

const response = await fetch('/files/upload', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});

const data = await response.json();
console.log('File ID:', data.data.file_id);
```

### Get File Details

```http
GET /files/{id}
Authorization: Bearer <token>
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "File retrieved",
  "data": {
    "id": 42,
    "name": "My Document",
    "filename": "my_document_1719669637_a1b2c3d4.pdf",
    "filepath": "/path/to/file.pdf",
    "size": 102400,
    "uploaded_by": 1,
    "uploaded_at": "2026-06-29T12:00:00+00:00"
  }
}
```

### Download File

```http
GET /files/{id}/download
Authorization: Bearer <token>
```

Downloads the file as attachment. Browser will prompt download.

**Response**: Binary file content with headers:
```
Content-Type: application/octet-stream
Content-Disposition: attachment; filename="document.pdf"
Content-Length: 102400
```

### List User's Files

```http
GET /files/my
Authorization: Bearer <token>
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Files retrieved",
  "data": [
    {
      "id": 42,
      "name": "My Document",
      "filename": "my_document_1719669637_a1b2c3d4.pdf",
      "size": 102400,
      "uploaded_by": 1,
      "uploaded_at": "2026-06-29T12:00:00+00:00"
    },
    {
      "id": 43,
      "name": "Invoice 2024",
      "filename": "invoice_1719669638_b2c3d4e5.pdf",
      "size": 204800,
      "uploaded_by": 1,
      "uploaded_at": "2026-06-29T12:05:00+00:00"
    }
  ]
}
```

### List All Files (Admin)

```http
GET /files
Authorization: Bearer <token>
```

Lists all files in system (admin only).

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Records found",
  "data": [
    {
      "id": 1,
      "name": "Report.pdf",
      "filename": "report_1719669600_a1b2c3d4.pdf",
      "size": 51200,
      "uploaded_by": 1,
      "uploaded_at": "2026-06-29T11:00:00+00:00"
    },
    ...
  ]
}
```

### Delete File

```http
DELETE /files/{id}
Authorization: Bearer <token>
```

Deletes file from disk and database.

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "File deleted successfully"
}
```

**Permissions**:
- Users can delete their own files
- Admins can delete any file

## Error Responses

| Status | Message | Cause |
|--------|---------|-------|
| 400 | Invalid file upload | Missing file fields |
| 400 | Upload error: ... | PHP upload error |
| 404 | File not found | Invalid file ID or missing on disk |
| 403 | You can only delete your own files | Non-admin user deleting others' file |
| 413 | File size exceeds maximum of 10MB | File too large |
| 415 | File type not allowed | Extension not whitelisted |
| 500 | Error uploading/downloading/deleting file | Database or I/O error |

## Database Schema

### files Table

```sql
CREATE TABLE `files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(256) NOT NULL COMMENT 'Display name',
  `filename` varchar(256) NOT NULL COMMENT 'Physical filename (sanitized, with timestamp)',
  `filepath` varchar(512) NOT NULL COMMENT 'Full path to file on disk',
  `size` int NOT NULL COMMENT 'File size in bytes',
  `uploaded_by` int NOT NULL COMMENT 'User ID who uploaded',
  `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `files_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;
```

## Implementation Details

### FileUploadManager Class

```php
$fileUploadManager = new FileUploadManager($db, $logger, '/path/to/uploads');

// Handle upload
$result = $fileUploadManager->handleUpload($_FILES['file'], $userId, 'Document Name');

// Get file
$file = $fileUploadManager->getFile($fileId);

// Download (get file and send to browser)
$fileUploadManager->downloadFile($fileId, $userId);

// List user's files
$files = $fileUploadManager->getUserFiles($userId);

// Delete file
$result = $fileUploadManager->deleteFile($fileId, $userId);
```

### Filename Generation

Files are stored with auto-generated names to prevent collisions:

```
original: invoice_2024.pdf
stored:   invoice_2024_1719669637_a1b2c3d4.pdf
          └─ original name ─┘└ timestamp ─┘└─ random ─┘
```

### Security Features

1. **Extension Whitelist**: Only specific extensions allowed
2. **Size Limit**: 10MB maximum per file
3. **Sanitized Names**: Special characters replaced with underscores
4. **User Isolation**: Users can only delete their own files (admins can delete any)
5. **Permission Checks**: RBAC enforced via table rules
6. **Audit Trail**: All uploads/downloads/deletions logged

## File Organization

```
/api/public/uploads/
├── document_2024_a1b2c3d4.pdf
├── invoice_2024_b2c3d4e5.pdf
├── photo_avatar_c3d4e5f6.jpg
└── backup_database_d4e5f6g7.zip
```

## Best Practices

### Upload Handling

1. **Handle large files**: For files >100MB, consider chunked upload (future enhancement)
2. **Validate server-side**: Always validate extension and size (not client-side only)
3. **Idempotent names**: Display name can be same for different uploads
4. **Disk space**: Monitor `/public/uploads/` disk usage

### Download Implementation

```php
// Redirect to download
header('Location: /files/' . $fileId . '/download');

// Or use in <a> tag
echo '<a href="/files/' . $fileId . '/download">Download</a>';
```

### Cleanup

Files are automatically deleted when:
- User deletes file via DELETE endpoint
- User is deleted (cascade delete via FOREIGN KEY)

Manual cleanup (e.g., orphaned files):
```bash
# Find files older than 90 days
find /api/public/uploads -type f -mtime +90

# Delete with verification (be careful!)
find /api/public/uploads -type f -mtime +90 -delete
```

## Limitations & Roadmap

### Current Limitations
- Synchronous upload (no chunking for large files)
- No virus scanning
- No image resizing/optimization
- No CDN integration
- No S3/cloud storage support

### Planned Features
- [ ] Chunked upload for large files
- [ ] Image thumbnail generation
- [ ] Virus scanning integration (ClamAV)
- [ ] S3/cloud storage backend
- [ ] File sharing with expiration
- [ ] Download rate limiting
- [ ] Bandwidth throttling
- [ ] File versioning

## Testing

### Unit Tests
```bash
./vendor/bin/phpunit tests/Unit/FileUploadManagerTest.php
```

Covers:
- File size validation
- Extension whitelist
- User ownership checks
- Permission enforcement

### Manual Testing

```bash
# Upload file
curl -X POST http://localhost:8000/files/upload \
  -H "Authorization: Bearer <token>" \
  -F "file=@test.pdf" \
  -F "name=Test Document"

# Get file details
curl http://localhost:8000/files/42 \
  -H "Authorization: Bearer <token>"

# Download file
curl -O -J http://localhost:8000/files/42/download \
  -H "Authorization: Bearer <token>"

# List my files
curl http://localhost:8000/files/my \
  -H "Authorization: Bearer <token>"

# Delete file
curl -X DELETE http://localhost:8000/files/42 \
  -H "Authorization: Bearer <token>"
```

## Storage Recommendations

### Production
- Store uploads on dedicated high-speed storage (SSD)
- Use `/uploads/` on separate partition to prevent filling root disk
- Regular backups of `/uploads/` directory
- Monitor disk usage with cron job
- Set up log rotation for large file operations

### Development
- Default location: `/api/public/uploads/`
- Can be customized in `public/index.php`
- Ensure directory is writable by web server

```php
// Custom upload directory
$fileUploadManager = new FileUploadManager(
    $db,
    $logger,
    '/var/uploads/splk2-files'
);
```
