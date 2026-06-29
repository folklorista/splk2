-- Initialize built-in roles (admin, user, guest)
-- Run this migration to set up the default role structure for RBAC

-- Check if roles already exist, if not insert them
INSERT IGNORE INTO `roles` (id, name, description) VALUES
(1, 'admin', 'Administrator - full system access, user management, role management'),
(2, 'user', 'Regular user - can create and manage own records, read others'),
(3, 'guest', 'Guest - read-only access to public records');
