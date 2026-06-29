-- Migration: Add soft delete support
-- Adds is_deleted flag to enable data recovery and audit trails

-- Users table
ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER updated_at;

-- Categories table
ALTER TABLE categories ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER updated_at;

-- Items table
ALTER TABLE items ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER updated_at;

-- Groups table
ALTER TABLE groups ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER updated_at;

-- Users Groups junction table
ALTER TABLE users_groups ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER created_at;

-- Create indices for faster filtering
CREATE INDEX idx_users_deleted ON users(is_deleted);
CREATE INDEX idx_categories_deleted ON categories(is_deleted);
CREATE INDEX idx_items_deleted ON items(is_deleted);
CREATE INDEX idx_groups_deleted ON groups(is_deleted);
CREATE INDEX idx_users_groups_deleted ON users_groups(is_deleted);

-- Optional: Create audit table for deleted records
CREATE TABLE IF NOT EXISTS deleted_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    deleted_by INT,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data JSON,
    restored_at TIMESTAMP NULL,
    restored_by INT NULL,
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_table_record (table_name, record_id)
);
