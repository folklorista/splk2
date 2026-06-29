<?php
namespace App;

class Endpoints
{
    private Database $db;
    private Auth $auth;
    private Logger $logger;
    private RuleValidator $validator;
    private WebhookManager $webhookManager;

    public function __construct(Database $db, Auth $auth, Logger $logger, RuleValidator $validator = null, WebhookManager $webhookManager = null)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->webhookManager = $webhookManager ?? new WebhookManager($db, $logger);
    }

    // Funkce pro logiku registrace
    public function registerUser($data)
    {
        if (!isset($data['email']) || !isset($data['password'])) {
            return Response::prepare(400, "Email and password required");
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return Response::prepare(400, "Invalid email format");
        }

        if (strlen($data['password']) < 8) {
            return Response::prepare(400, "Password must be at least 8 characters long");
        }

        if (!isset($data['first_name']) || !isset($data['last_name'])) {
            return Response::prepare(400, "First name and last name required");
        }

        if (strlen($data['first_name']) < 2 || strlen($data['last_name']) < 2) {
            return Response::prepare(400, "First name and last name must be at least 2 characters long");
        }

        if (strlen($data['first_name']) > 64 || strlen($data['last_name']) > 64) {
            return Response::prepare(400, "First name and last name must be at most 64 characters long");
        }

        $existingUserResponse = $this->db->get('users', $data['email'], 'email');
        if ($existingUserResponse && $existingUserResponse['status'] === 200) {
            return Response::prepare(400, "Email already exists");
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        $newUser = [
            'email' => $data['email'],
            'password' => $hashedPassword,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ];
        $userIdResponse = $this->db->insert('users', $newUser);
        if ($userIdResponse && $userIdResponse['status'] === 200) {
            $userIdResponse['message'] = "User registered";
        }
        return $userIdResponse;

    }

    // Funkce pro logiku přihlášení (login)
    public function loginUser($data)
    {
        if (isset($data['email']) && isset($data['password'])) {
            if ($this->auth->verifyPassword($data['email'], $data['password'])) {
                $userResponse = $this->db->get('users', $data['email'], 'email');
                if (!$userResponse || $userResponse['status'] !== 200) {
                    return Response::prepare(401, "Invalid credentials");
                }
                $token = $this->auth->generateToken($userResponse['data']);
                $this->db->logAction(AuditAction::USER_LOGIN, $userResponse['data']['id']); // Přihlášení uživatele
                return Response::prepare(200, "User logged in", ['token' => $token]);
            }
            return Response::prepare(401, "Invalid credentials");
        }
        return Response::prepare(400, "Username and password required");
    }

    // Funkce pro GET operaci - záznamy podle ID
    public function getRecordByIdEndpoint(string $table, int $id)
    {
        return $this->db->get($table, $id);
    }

    public function getAllRecords(
        string $table, 
        string $whereClause = "", 
        int $limit = null, 
        int $offset = null, 
        string $orderBy = null, 
        string $orderDir = 'ASC',
        string $searchQuery = null,
        array $searchColumns = null
    ) {
        return $this->db->getAll($table, $whereClause, $limit, $offset, $orderBy, $orderDir, $searchQuery, $searchColumns);
    }
    
    // Funkce pro POST operaci - vytvoření nového záznamu
    public function createRecordEndpoint($table, $data, $user)
    {
        // Validation
        if ($this->validator) {
            $validation = $this->validator->validateCreate($table, $data);
            if (!$validation['valid']) {
                return Response::prepare(400, "Validation failed", null,
                    implode('; ', $validation['errors']));
            }
        }

        // Hook: beforeCreate
        if ($this->validator) {
            try {
                $this->validator->executeHook($table, 'beforeCreate', $data, $user, $this->logger);
            } catch (RuleException $e) {
                return Response::prepare($e->getCode(), $e->getMessage());
            } catch (\Exception $e) {
                return Response::prepare(400, $e->getMessage());
            }
        }

        // Hash passwords
        if (isset($data['password'])) {
            $data['password'] = $this->auth->hashPassword($data['password']);
        }

        // Insert
        $response = $this->db->insert($table, $data);
        if ($response['status'] !== 201) {
            return $response;
        }

        // Hook: afterCreate
        if ($this->validator) {
            try {
                $this->validator->executeHook($table, 'afterCreate', $response['data']['id'],
                    $user, $this->logger, $this->db);
            } catch (\Exception $e) {
                $this->logger->error("AfterCreate hook failed", ['error' => $e->getMessage()]);
            }
        }

        // Audit log (CREATE: new_values = data)
        $this->db->logAction(
            AuditAction::DATA_INSERT,
            $user->id,
            $table,
            $response['data']['id'],
            $response['message'],
            $data,
            oldValues: null,
            newValues: $data
        );

        // Trigger webhook event
        try {
            $this->webhookManager->triggerEvent(
                "{$table}.created",
                $response['data']['id'],
                [
                    'table' => $table,
                    'record_id' => $response['data']['id'],
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error("Webhook trigger failed for create", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    // Funkce pro PUT operaci - aktualizace záznamu
    public function updateRecordEndpoint($table, $id, $data, $user)
    {
        // Fetch old values before update (for change tracking)
        $oldRecordResponse = $this->db->get($table, $id);
        $oldValues = ($oldRecordResponse['status'] === 200) ? $oldRecordResponse['data'] : [];

        // Validation
        if ($this->validator) {
            $validation = $this->validator->validateUpdate($table, $data);
            if (!$validation['valid']) {
                return Response::prepare(400, "Validation failed", null,
                    implode('; ', $validation['errors']));
            }
        }

        // Hook: beforeUpdate
        if ($this->validator) {
            try {
                $this->validator->executeHook($table, 'beforeUpdate', $id, $data,
                    $user, $this->logger, $this->db);
            } catch (RuleException $e) {
                return Response::prepare($e->getCode(), $e->getMessage());
            } catch (\Exception $e) {
                return Response::prepare(400, $e->getMessage());
            }
        }

        // Hash passwords
        if (isset($data['password'])) {
            $data['password'] = $this->auth->hashPassword($data['password']);
        }

        // Update
        $response = $this->db->update($table, $id, $data);
        if ($response['status'] !== 200) {
            return $response;
        }

        // Hook: afterUpdate
        if ($this->validator) {
            try {
                $this->validator->executeHook($table, 'afterUpdate', $id,
                    $user, $this->logger, $this->db);
            } catch (\Exception $e) {
                $this->logger->error("AfterUpdate hook failed", ['error' => $e->getMessage()]);
            }
        }

        // Audit log (UPDATE: old_values + new_values)
        $this->db->logAction(
            AuditAction::DATA_UPDATE,
            $user->id,
            $table,
            $id,
            $response['message'],
            $data,
            oldValues: $oldValues,
            newValues: array_merge($oldValues, $data)
        );

        // Trigger webhook event
        try {
            $this->webhookManager->triggerEvent(
                "{$table}.updated",
                $id,
                [
                    'table' => $table,
                    'record_id' => $id,
                    'old_values' => $oldValues,
                    'new_values' => $data,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error("Webhook trigger failed for update", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    // Funkce pro DELETE operaci - smazání záznamu
    public function deleteRecordEndpoint($table, $id, $user)
    {
        // Fetch old values before delete (for change tracking)
        $oldRecordResponse = $this->db->get($table, $id);
        $oldValues = ($oldRecordResponse['status'] === 200) ? $oldRecordResponse['data'] : [];

        // Hook: beforeDelete
        if ($this->validator) {
            try {
                $this->validator->executeHook($table, 'beforeDelete', $id,
                    $user, $this->logger, $this->db);
            } catch (RuleException $e) {
                return Response::prepare($e->getCode(), $e->getMessage());
            } catch (\Exception $e) {
                return Response::prepare(400, $e->getMessage());
            }
        }

        // Delete
        $response = $this->db->delete($table, $id);
        if ($response['status'] !== 200) {
            return $response;
        }

        // Hook: afterDelete
        if ($this->validator) {
            try {
                $this->validator->executeHook($table, 'afterDelete', $id,
                    $user, $this->logger, $this->db);
            } catch (\Exception $e) {
                $this->logger->error("AfterDelete hook failed", ['error' => $e->getMessage()]);
            }
        }

        // Audit log (DELETE: old_values)
        $this->db->logAction(
            AuditAction::DATA_DELETE,
            $user->id,
            $table,
            $id,
            $response['message'],
            data: null,
            oldValues: $oldValues,
            newValues: null
        );

        // Trigger webhook event
        try {
            $this->webhookManager->triggerEvent(
                "{$table}.deleted",
                $id,
                [
                    'table' => $table,
                    'record_id' => $id,
                    'old_values' => $oldValues,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error("Webhook trigger failed for delete", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    public function handleForeignKeys($table, $queryParams)
    {
        // Sanitize and validate foreign keys to prevent SQL injection
        $conditions = [];
        $params = [];
        $paramIndex = 0;

        foreach ($queryParams as $key => $value) {
            if ($this->db->isForeignKey($table, $key)) {
                // Use prepared statement parameters instead of direct interpolation
                $paramName = ':fk_' . $paramIndex++;
                $conditions[] = "`{$key}` = {$paramName}";
                $params[$paramName] = $value;
            }
        }

        if (empty($conditions)) {
            Response::send(400, 'No valid foreign keys provided.');
            return;
        }

        $whereClause = implode(' AND ', $conditions);

        // Volání funkce getAll s bezpečnými parametry
        $result = $this->db->getAllWhere($table, $whereClause, $params);

        // Zpracování výsledku z getAll
        if ($result['status'] === 200) {
            Response::sendPrepared(Response::prepare(200, 'Records found', $result['data']));
        } elseif ($result['status'] === 204) {
            Response::send(204, 'No records found');
        } else {
            Response::send(500, 'Error retrieving records', null, $result['error'] ?? 'Unknown error');
        }
    }

    public function getTree(string $tableName): array
    {
        // Načte data přes třídu Database
        $records = $this->db->getAllTreeRecords($tableName);

        // Převod na stromovou strukturu
        return $this->buildTree($tableName, $records);
    }

    private function buildTree(string $tableName, array $records, int $parentId = null): array
    {
        $tree = [];
        foreach ($records as $record) {
            if ($record['parent_id'] === $parentId) {
                $children = $this->buildTree($tableName, $records, $record['id']);
                $tree[] = [
                    'id' => (string) $record['id'],
                    'name' => $record['name'],
                    'children' => $children,
                ];
            }
        }
        return $tree;
    }

    private function flattenTree(array $trees, int $parentId = null): array
    {
        $flat = [];

        // Iterace přes všechny kořenové uzly
        foreach ($trees as $tree) {
            // Zpracování aktuálního uzlu stromu
            $flat[] = [
                'id' => $tree['id'] ?? null, // Ošetření chybějícího ID
                'name' => $tree['name'] ?? 'Unknown', // Ošetření chybějícího názvu
                'parent_id' => $parentId,
            ];

            // Pokud uzel obsahuje děti, zpracuj je rekurzivně
            if (!empty($tree['children']) && is_array($tree['children'])) {
                $flat = array_merge($flat, $this->flattenTree($tree['children'], $tree['id'] ?? null));
            }
        }

        return $flat;
    }

    public function loadTreeEndpoint(string $tableName): void
    {
        $tree = $this->getTree($tableName);
        Response::send(200, 'Records found', $tree);
    }

    public function saveOrUpdateTree(string $tableName, array $tree, ?int $parentId = null): void
    {
        $records = $this->flattenTree($tree, $parentId);

        foreach ($records as $index => $record) {
            // Zkontrolujeme, zda má záznam platné jméno
            if (empty($record['name'])) {
                throw new \InvalidArgumentException("Položka musí mít název.");
            }

            // Zpracování ID (existuje nebo ne)
            $recordId = null;

            if (!empty($record['id'])) {
                $exists = $this->db->treeRecordExists($tableName, $record['id']);
                if ($exists) {
                    // Aktualizace existujícího záznamu
                    $this->db->updateTreeRecord($tableName, [
                        'id' => intval($record['id']),
                        'name' => $record['name'],
                        'parent_id' => $record['parent_id'],
                        'position' => $index,
                    ]);
                    $recordId = $record['id'];
                } else {
                    // ID neexistuje – vytvoříme nový záznam
                    $recordId = $this->db->insertTreeRecord($tableName, [
                        'name' => $record['name'],
                        'parent_id' => $record['parent_id'],
                        'position' => $index,
                    ]);
                }
            } else {
                // Vytvoříme nový záznam
                $treeRecordId = $this->db->insertTreeRecord($tableName, [
                    'name' => $record['name'],
                    'parent_id' => $record['parent_id'],
                    'position' => $index,
                ]);
            }

            // Rekurzivní zpracování dětí, pokud existují
            if (!empty($record['children']) && is_array($record['children'])) {
                $this->saveOrUpdateTree($tableName, $record['children'], $recordId);
            }
        }
    }

    public function treeSaveOrUpdateEndpoint(string $tableName, $data, $user): void
    {
        if (!$data || !is_array($data)) {
            $message = 'Invalid data';
            $this->db->logAction(AuditAction::TREE_UPDATE, $user->id, $tableName, null, $message, $data);
            Response::send(400, $message, $data);
            return;
        }

        // Aktualizace nebo uložení nového stromu
        $this->saveOrUpdateTree($tableName, $data);

        $message = 'Tree structure saved or updated successfully';
        $this->db->logAction(AuditAction::TREE_UPDATE, $user->id, $tableName, null, $message, $data);
        Response::send(200, $message, $data);
    }

    /**
     * Get all roles for a user
     */
    public function getUserRolesEndpoint(int $userId)
    {
        try {
            $userResult = $this->db->get('users', $userId);
            if ($userResult['status'] !== 200) {
                return Response::prepare(404, "User not found");
            }

            $rolesResult = $this->db->getAllWhere('users_roles', 'user_id = ?', [$userId]);
            if ($rolesResult['status'] !== 200) {
                return Response::prepare(200, "No roles assigned", []);
            }

            $roles = [];
            foreach ($rolesResult['data'] as $userRole) {
                $roleResult = $this->db->get('roles', $userRole['role_id']);
                if ($roleResult['status'] === 200) {
                    $roles[] = $roleResult['data'];
                }
            }

            $this->logger->info("User roles retrieved", ['user_id' => $userId]);
            return Response::prepare(200, "User roles retrieved", $roles);
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving user roles", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return Response::prepare(500, "Error retrieving user roles");
        }
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUserEndpoint(int $userId, int $roleId, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can assign roles");
            }

            $result = $rbac->assignRole($userId, $roleId);
            $this->db->logAction(AuditAction::CREATE, $user->id, 'users_roles', null, "Role assigned to user", ['user_id' => $userId, 'role_id' => $roleId]);
            return Response::prepare($result['status'], $result['message']);
        } catch (\Exception $e) {
            $this->logger->error("Error assigning role", [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);
            return Response::prepare(500, "Error assigning role");
        }
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUserEndpoint(int $userId, int $roleId, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can remove roles");
            }

            $result = $rbac->removeRole($userId, $roleId);
            $this->db->logAction(AuditAction::DELETE, $user->id, 'users_roles', null, "Role removed from user", ['user_id' => $userId, 'role_id' => $roleId]);
            return Response::prepare($result['status'], $result['message']);
        } catch (\Exception $e) {
            $this->logger->error("Error removing role", [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);
            return Response::prepare(500, "Error removing role");
        }
    }

    /**
     * Create webhook
     */
    public function createWebhookEndpoint(array $data, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can create webhooks");
            }

            $url = $data['url'] ?? null;
            $events = $data['events'] ?? [];

            if (!$url || !$events) {
                return Response::prepare(400, "URL and events are required");
            }

            $result = $this->webhookManager->createWebhook($user->id, $url, $events);
            return Response::prepare($result['status'], $result['message'], $result['data']);
        } catch (\Exception $e) {
            $this->logger->error("Error creating webhook", ['error' => $e->getMessage()]);
            return Response::prepare(500, "Error creating webhook");
        }
    }

    /**
     * Get all webhooks for authenticated user
     */
    public function getWebhooksEndpoint($user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can view webhooks");
            }

            $result = $this->webhookManager->getUserWebhooks($user->id);
            return Response::prepare($result['status'], $result['message'], $result['data']);
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving webhooks", ['error' => $e->getMessage()]);
            return Response::prepare(500, "Error retrieving webhooks");
        }
    }

    /**
     * Get single webhook details
     */
    public function getWebhookEndpoint(int $webhookId, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can view webhooks");
            }

            $result = $this->webhookManager->getWebhook($webhookId);
            return Response::prepare($result['status'], $result['message'], $result['data']);
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving webhook", ['error' => $e->getMessage()]);
            return Response::prepare(500, "Error retrieving webhook");
        }
    }

    /**
     * Update webhook
     */
    public function updateWebhookEndpoint(int $webhookId, array $data, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can update webhooks");
            }

            $result = $this->webhookManager->updateWebhook($webhookId, $data);
            return Response::prepare($result['status'], $result['message']);
        } catch (\Exception $e) {
            $this->logger->error("Error updating webhook", ['error' => $e->getMessage()]);
            return Response::prepare(500, "Error updating webhook");
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhookEndpoint(int $webhookId, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can delete webhooks");
            }

            $result = $this->webhookManager->deleteWebhook($webhookId);
            return Response::prepare($result['status'], $result['message']);
        } catch (\Exception $e) {
            $this->logger->error("Error deleting webhook", ['error' => $e->getMessage()]);
            return Response::prepare(500, "Error deleting webhook");
        }
    }

    /**
     * Test webhook
     */
    public function testWebhookEndpoint(int $webhookId, $user)
    {
        try {
            $rbac = new RoleBasedAccessControl($this->db, $this->logger);

            // Check if current user is admin
            if (!$rbac->hasRole($user, 'admin')) {
                return Response::prepare(403, "Only administrators can test webhooks");
            }

            $result = $this->webhookManager->testWebhook($webhookId);
            return Response::prepare($result['status'], $result['message'], $result['data']);
        } catch (\Exception $e) {
            $this->logger->error("Error testing webhook", ['error' => $e->getMessage()]);
            return Response::prepare(500, "Error testing webhook");
        }
    }

}
