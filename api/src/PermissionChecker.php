<?php
namespace App;

class PermissionChecker
{
    private $permissions;
    private $rbac;
    private $logger;

    public function __construct(array $permissions, RoleBasedAccessControl $rbac, Logger $logger)
    {
        $this->permissions = $permissions;
        $this->rbac = $rbac;
        $this->logger = $logger;
    }

    /**
     * Check if user can perform action on table
     */
    public function canAccess(
        string $table,
        string $action,
        ?object $user,
        ?int $recordOwnerId = null
    ): array {
        if (!$user) {
            return [
                'allowed' => false,
                'reason' => 'Authentication required',
            ];
        }

        // Get user's roles
        $userRoles = $this->getUserRoles($user);

        // Get permissions for this table
        $tablePerms = $this->getTablePermissions($table);

        // Check each user role for permission
        foreach ($userRoles as $role) {
            $rolePerms = $tablePerms['permissions'][$role] ?? null;
            if (!$rolePerms) {
                continue;
            }

            // Check if action is allowed
            if (!$rolePerms[$action] ?? false) {
                continue;
            }

            // Check ownership restrictions
            $ownerOnlyField = "{$action}_own_only";
            if ($rolePerms[$ownerOnlyField] ?? false) {
                // User can only access own records
                if ($recordOwnerId === null) {
                    // Reading list - will be filtered by recordOwnerId
                    return ['allowed' => true, 'filtered' => true];
                }

                if ($recordOwnerId != $user->id) {
                    $this->logger->warning("Authorization denied: user trying to access someone else's record", [
                        'user_id' => $user->id,
                        'table' => $table,
                        'action' => $action,
                        'record_owner_id' => $recordOwnerId,
                    ]);
                    return [
                        'allowed' => false,
                        'reason' => 'You can only access your own records',
                    ];
                }
            }

            $this->logger->info("Authorization granted", [
                'user_id' => $user->id,
                'table' => $table,
                'action' => $action,
                'role' => $role,
                'record_owner_id' => $recordOwnerId,
            ]);

            return ['allowed' => true];
        }

        $this->logger->warning("Authorization denied: no role with permission", [
            'user_id' => $user->id,
            'table' => $table,
            'action' => $action,
            'roles' => $userRoles,
        ]);

        return [
            'allowed' => false,
            'reason' => 'You do not have permission to ' . $action . ' ' . $table,
        ];
    }

    /**
     * Get permissions for a specific table
     */
    private function getTablePermissions(string $table): array
    {
        return $this->permissions[$table] ?? $this->permissions['default'];
    }

    /**
     * Get owner field for table
     */
    public function getOwnerField(string $table): string
    {
        $tablePerms = $this->getTablePermissions($table);
        return $tablePerms['owner_field'] ?? 'created_by';
    }

    /**
     * Get user's roles
     */
    private function getUserRoles(object $user): array
    {
        if (!isset($user->id)) {
            return ['guest'];
        }

        $roles = [];

        // Check if admin
        if ($this->rbac->hasRole($user, 'admin')) {
            $roles[] = 'admin';
        }

        // Check if user role
        if ($this->rbac->hasRole($user, 'user')) {
            $roles[] = 'user';
        }

        // Add guest if no other roles
        if (empty($roles)) {
            $roles[] = 'guest';
        }

        return $roles;
    }

    /**
     * Filter query to only show records user can access
     * Returns WHERE clause that restricts to user's own records if needed
     */
    public function getFilterForReadAccess(string $table, object $user): ?string
    {
        $userRoles = $this->getUserRoles($user);
        $tablePerms = $this->getTablePermissions($table);
        $ownerField = $tablePerms['owner_field'];

        // Check if user needs to be filtered
        foreach ($userRoles as $role) {
            $rolePerms = $tablePerms['permissions'][$role] ?? null;
            if (!$rolePerms) {
                continue;
            }

            // If this role can read all records, no filter needed
            if (!($rolePerms['read_own_only'] ?? false)) {
                return null;
            }
        }

        // All roles require own-only access, so filter
        return "{$ownerField} = {$user->id}";
    }

    /**
     * Get all permissions for a role on a table
     */
    public function getPermissionsForRole(string $table, string $role): ?array
    {
        $tablePerms = $this->getTablePermissions($table);
        return $tablePerms['permissions'][$role] ?? null;
    }
}
