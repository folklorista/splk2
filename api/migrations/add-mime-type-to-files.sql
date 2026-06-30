-- Add MIME type tracking to files table
ALTER TABLE files ADD COLUMN IF NOT EXISTS mime_type VARCHAR(255) AFTER filepath;
