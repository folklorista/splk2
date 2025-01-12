<?php
namespace App;

class Endpoints
{
    private Database $db;
    private Auth $auth;
    private Logger $logger;

    public function __construct(Database $db, Auth $auth, Logger $logger)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->logger = $logger;
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

    public function getAllRecords($table)
    {
        return $this->db->getAll($table);
    }

    // Funkce pro POST operaci - vytvoření nového záznamu
    public function createRecordEndpoint($table, $data, $user)
    {
        if (isset($data['password'])) {
            $data['password'] = $this->auth->hashPassword($data['password']);
        }

        $response = $this->db->insert($table, $data);
        $this->db->logAction(AuditAction::DATA_INSERT, $user->id, $table, $response['data']['id'], $response['message'], $data);

        return $response;
    }

    // Funkce pro PUT operaci - aktualizace záznamu
    public function updateRecordEndpoint($table, $id, $data, $user)
    {
        if (isset($data['password'])) {
            $data['password'] = $this->auth->hashPassword($data['password']);
        }
        $response = $this->db->update($table, $id, $data);
        $this->db->logAction(AuditAction::DATA_UPDATE, $user->id, $table, $id, $response['message'], $data);
        return $response;
    }

    // Funkce pro DELETE operaci - smazání záznamu
    public function deleteRecordEndpoint($table, $id, $user)
    {
        $response = $this->db->delete($table, $id);
        $this->db->logAction(AuditAction::DATA_DELETE, $user->id, $table, $id, $response['message']);
        return $response;
    }

    public function handleForeignKeys($table, $queryParams)
    {
        // Sestavení podmínek WHERE na základě queryParams
        $conditions = [];
        foreach ($queryParams as $key => $value) {
            if ($this->db->isForeignKey($table, $key)) {
                $conditions[] = "`$key` = '$value'";
            }
        }

        if (empty($conditions)) {
            Response::send(400, 'No valid foreign keys provided.');
            return;
        }

        $whereClause = implode(' AND ', $conditions);

        // Volání funkce getAll s generovaným WHERE
        $result = $this->db->getAll($table, $whereClause);

        // Zpracování výsledku z getAll
        if ($result['statusCode'] === 200) {
            Response::send(200, 'Records found', $result['data']);
        } elseif ($result['statusCode'] === 204) {
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

}
