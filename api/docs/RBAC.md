# Role-Based Access Control (RBAC)

## Overview

SPLK2 API implements role-based access control to enforce security policies on operations. Users are assigned roles (admin, user, guest) that determine what actions they can perform.

## Built-in Roles

### Admin
- **ID**: 1
- **Description**: Administrator - full system access, user management, role management
- **Permissions**:
  - Create/Update/Delete users
  - Create/Update/Delete roles
  - Assign/Remove roles to/from users
  - Full access to all data

### User
- **ID**: 2
- **Description**: Regular user - can create and manage own records, read others
- **Permissions**:
  - Create new records (users, items, etc.)
  - Update own profile
  - Read all records
  - Cannot delete users or manage roles

### Guest
- **ID**: 3
- **Description**: Guest - read-only access
- **Permissions**:
  - Read public records only
  - No write permissions

## Enforced Policies

### User Management

#### POST /users (Create User)
- **Restriction**: Admin only
- **Error**: 403 Forbidden
- **Message**: "Only administrators can create users"

#### PUT /users/{id} (Update User)
- **Restriction**: Admin can update anyone, users can only update themselves
- **Error**: 403 Forbidden
- **Message**: "You can only update your own account"

#### DELETE /users/{id} (Delete User)
- **Restriction**: Admin only
- **Error**: 403 Forbidden
- **Message**: "Only administrators can delete users"
- **Additional**: Cannot delete own account

### Role Management

#### POST /roles (Create Role)
- **Restriction**: Admin only
- **Error**: 403 Forbidden
- **Message**: "Only administrators can create roles"

#### PUT /roles/{id} (Update Role)
- **Restriction**: Admin only
- **Error**: 403 Forbidden
- **Message**: "Only administrators can update roles"

#### DELETE /roles/{id} (Delete Role)
- **Restriction**: Admin only
- **Error**: 403 Forbidden
- **Message**: "Only administrators can delete roles"
- **Additional**: Cannot delete built-in roles (admin, user, guest)

## Role Assignment Endpoints

### GET /users/{id}/roles
Get all roles assigned to a user.

```bash
curl -H "Authorization: Bearer <token>" \
  http://localhost:8000/users/1/roles
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "User roles retrieved",
  "data": [
    {
      "id": 1,
      "name": "admin",
      "description": "Administrator - full system access"
    }
  ]
}
```

### POST /users/{id}/roles/{roleId}
Assign a role to a user (Admin only).

```bash
curl -X POST -H "Authorization: Bearer <token>" \
  http://localhost:8000/users/1/roles/2
```

**Request Body** (optional):
```json
{
  "role_id": 2
}
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Role assigned successfully"
}
```

### DELETE /users/{id}/roles/{roleId}
Remove a role from a user (Admin only).

```bash
curl -X DELETE -H "Authorization: Bearer <token>" \
  http://localhost:8000/users/1/roles/2
```

**Response** (200 OK):
```json
{
  "status": 200,
  "message": "Role removed successfully"
}
```

## Implementation Details

### RoleBasedAccessControl Class

The `RoleBasedAccessControl` class handles all role-related operations:

```php
$rbac = new RoleBasedAccessControl($db, $logger);

// Check if user has role
$isAdmin = $rbac->hasRole($user, 'admin');

// Check multiple roles (OR logic)
$isAdminOrMod = $rbac->hasRole($user, ['admin', 'moderator']);

// Assign role
$result = $rbac->assignRole($userId, $roleId);

// Remove role
$result = $rbac->removeRole($userId, $roleId);

// Check ownership
$isOwner = $rbac->isOwner($userId, 'users', $recordId);
```

### Table Rules Integration

RBAC is integrated via table rules in `config/table-rules.php`:

```php
'users' => [
    'hooks' => [
        'beforeCreate' => function($data, $user, $logger, $db) {
            $rbac = new RoleBasedAccessControl($db, $logger);
            if (!$rbac->hasRole($user, 'admin')) {
                throw new RuleException('Only administrators can create users', 403);
            }
        }
    ]
]
```

## Database Schema

### users_roles Table
```sql
CREATE TABLE `users_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'ID uživatele',
  `role_id` int NOT NULL COMMENT 'ID role',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_role_unique` (`user_id`,`role_id`),
  KEY `user_role_role_fk` (`role_id`),
  CONSTRAINT `user_role_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_role_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### roles Table
```sql
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT 'název role',
  `description` text COMMENT 'popis role',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Testing

### Unit Tests
```bash
./vendor/bin/phpunit tests/Unit/RoleBasedAccessControlTest.php
```

Covers:
- hasRole() functionality
- Role assignment/removal
- Ownership checks
- Error handling

### E2E Tests
```bash
./vendor/bin/phpunit tests/E2E/RBACTest.php
```

Covers:
- Admin can create/delete users
- Regular users cannot create/delete users
- Users can only update themselves
- Admin cannot delete own account
- Role management restrictions

## Migration

Initialize built-in roles:
```bash
mysql -u root -p your_database < api/migrations/init-built-in-roles.sql
```

Or manually:
```sql
INSERT INTO roles (id, name, description) VALUES
(1, 'admin', 'Administrator - full system access'),
(2, 'user', 'Regular user - can manage own records'),
(3, 'guest', 'Guest - read-only access');
```

Assign admin role to a user:
```sql
INSERT INTO users_roles (user_id, role_id) VALUES (1, 1);
```

## Error Codes

| Code | Message | Cause |
|------|---------|-------|
| 403 | Only administrators can... | User lacks required role |
| 403 | You can only update your own account | User trying to update others |
| 403 | You cannot delete your own account | Self-deletion attempt |
| 403 | Cannot delete built-in roles | Attempting to delete admin/user/guest |
| 404 | User not found | Invalid user_id |
| 404 | Role not found | Invalid role_id |
| 400 | User already has this role | Duplicate role assignment |
| 500 | Error assigning role | Database error |

## Future Enhancements

- Permission-based access (more granular than roles)
- Resource-level permissions (can I read/write specific items?)
- Webhook system for external authorization
- Session-based role revocation
