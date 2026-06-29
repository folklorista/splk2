-- Migration: Add change tracking to audit logs
-- Adds old_values and new_values JSON columns for detailed audit trail

ALTER TABLE audit_logs
ADD COLUMN old_values JSON DEFAULT NULL COMMENT 'Stará hodnota před změnou' AFTER data,
ADD COLUMN new_values JSON DEFAULT NULL COMMENT 'Nová hodnota po změně' AFTER old_values;

CREATE INDEX idx_audit_table_record ON audit_logs(table_name, record_id);
