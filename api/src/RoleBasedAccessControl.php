<?php
namespace App;

class RoleBasedAccessControl {
    private $db;
    private $logger;

    public function __construct(Database $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Check if user has required role
     *
     * @param object $user User object with id
     * @param string|array $requiredRole Single role name or array of role names
     * @return bool
     */
    public function hasRole($user, $requiredRole): bool {
        if (!$user || !isset($user->id)) {
            return false;
        }

        $roles = $this->getUserRoles($user->id);
        $roleNames = array_map(fn($r) => $r['name'], $roles);

        if (is_array($requiredRole)) {
            return count(array_intersect($roleNames, $requiredRole)) > 0;
        }

        return in_array($requiredRole, $roleNames);
    }

    /**
     * Get all roles for a user
     *
     * @param int $userId
     * @return array
     */
    private function getUserRoles(int $userId): array {
        try {
            $result = $this->db->getAllWhere(
                'users_roles',
                'user_id = ?',
                [$userId]
            );

            if ($result['status'] !== 200 || empty($result['data'])) {
                return [];
            }

            $roles = [];
            foreach ($result['data'] as $userRole) {
                $roleResult = $this->db->get('roles', $userRole['role_id']);
                if ($roleResult['status'] === 200) {
                    $roles[] = $roleResult['data'];
                }
            }

            return $roles;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching user roles', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Assign role to user
     *
     * @param int $userId
     * @param int $roleId
     * @return array Status response
     */
    public function assignRole(int $userId, int $roleId): array {
        try {
            // Verify user exists
            $userResult = $this->db->get('users', $userId);
            if ($userResult['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            // Verify role exists
            $roleResult = $this->db->get('roles', $roleId);
            if ($roleResult['status'] !== 200) {
                return [
                    'status' => 404,
                    'message' => 'Role not found',
                    'data' => null,
                ];
            }

            // Check if assignment already exists
            $existingResult = $this->db->getAllWhere(
                'users_roles',
                'user_id = ? AND role_id = ?',
                [$userId, $roleId]
            );

            if ($existingResult['status'] === 200 && !empty($existingResult['data'])) {
                return [
                    'status' => 400,
                    'message' => 'User already has this role',
                    'data' => null,
                ];
            }

            // Assign role
            $this->db->execute(
                'INSERT INTO users_roles (user_id, role_id) VALUES (?, ?)',
                [$userId, $roleId]
            );

            $this->logger->info('Role assigned to user', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'role_name' => $roleResult['data']['name'],
            ]);

            return [
                'status' => 200,
                'message' => 'Role assigned successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error assigning role', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error assigning role',
                'data' => null,
            ];
        }
    }

    /**
     * Remove role from user
     *
     * @param int $userId
     * @param int $roleId
     * @return array Status response
     */
    public function removeRole(int $userId, int $roleId): array {
        try {
            // Check if assignment exists
            $existingResult = $this->db->getAllWhere(
                'users_roles',
                'user_id = ? AND role_id = ?',
                [$userId, $roleId]
            );

            if ($existingResult['status'] !== 200 || empty($existingResult['data'])) {
                return [
                    'status' => 404,
                    'message' => 'User does not have this role',
                    'data' => null,
                ];
            }

            // Remove role
            $this->db->execute(
                'DELETE FROM users_roles WHERE user_id = ? AND role_id = ?',
                [$userId, $roleId]
            );

            $this->logger->info('Role removed from user', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);

            return [
                'status' => 200,
                'message' => 'Role removed successfully',
                'data' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error removing role', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Error removing role',
                'data' => null,
            ];
        }
    }

    /**
     * Check if user is owner of a record (for PUT/DELETE own data)
     *
     * @param int $userId
     * @param string $table
     * @param int $recordId
     * @return bool
     */
    public function isOwner(int $userId, string $table, int $recordId): bool {
        try {
            if ($table !== 'users') {
                return false;
            }

            return $userId === $recordId;
        } catch (\Exception $e) {
            $this->logger->error('Error checking ownership', [
                'user_id' => $userId,
                'table' => $table,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
