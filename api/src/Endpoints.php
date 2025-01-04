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
                return Response::prepare(200, "Logged in", ['token' => $token]);
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
    public function createRecordEndpoint($table, $data)
    {
        if (isset($data['password'])) {
            $data['password'] = $this->auth->hashPassword($data['password']);
        }
        return $this->db->insert($table, $data);
    }

    // Funkce pro PUT operaci - aktualizace záznamu
    public function updateRecordEndpoint($table, $id, $data)
    {
        if (isset($data['password'])) {
            $data['password'] = $this->auth->hashPassword($data['password']);
        }
        return $this->db->update($table, $id, $data);
    }

    // Funkce pro DELETE operaci - smazání záznamu
    public function deleteRecordEndpoint($table, $id)
    {
        return $this->db->delete($table, $id);
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

    public function getCategoriesTree(): array
    {
        // Načte data přes třídu Database
        $categories = $this->db->getAllCategories();

        // Převod na stromovou strukturu
        return $this->buildCategoriesTree($categories);
    }

    private function buildCategoriesTree(array $categories, int $parentId = null): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] === $parentId) {
                $children = $this->buildCategoriesTree($categories, $category['id']);
                $tree[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'children' => $children,
                ];
            }
        }
        return $tree;
    }

    private function flattenCategoriesTree(array $tree, int $parentId = null): array
    {
        $flat = [];
    
        // Zpracování aktuálního uzlu stromu
        $flat[] = [
            'id' => $tree['id'] ?? null, // Ošetření chybějícího ID
            'name' => $tree['name'] ?? 'Unknown', // Ošetření chybějícího názvu
            'parent_id' => $parentId,
        ];
    
        // Pokud uzel obsahuje děti, zpracuj je rekurzivně
        if (!empty($tree['children']) && is_array($tree['children'])) {
            foreach ($tree['children'] as $child) {
                $flat = array_merge($flat, $this->flattenCategoriesTree($child, $tree['id'] ?? null));
            }
        }
    
        return $flat;
    }

    public function categoriesEndpoint(): void
    {
        $tree = $this->getCategoriesTree();
        Response::send(200, 'Records found', $tree);
    }

    public function saveOrUpdateCategoriesTree(array $tree, ?int $parentId = null): void
    {
        $categories = $this->flattenCategoriesTree($tree, $parentId);

        foreach ($categories as $index => $category) {
            // Zkontrolujeme, zda má kategorie platné jméno
            if (empty($category['name'])) {
                throw new \InvalidArgumentException("Kategorie musí mít název.");
            }

            // Zpracování ID (existuje nebo ne)
            $categoryId = null;

            if (!empty($category['id'])) {
                $exists = $this->db->categoryExists($category['id']);
                if ($exists) {
                    // Aktualizace existující kategorie
                    $this->db->updateCategory([
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'parent_id' => $category['parent_id'],
                        'position' => $index,
                    ]);
                    $categoryId = $category['id'];
                } else {
                    // ID neexistuje – vytvoříme nový záznam
                    $categoryId = $this->db->insertCategory([
                        'name' => $category['name'],
                        'parent_id' => $category['parent_id'],
                        'position' => $index,
                    ]);
                }
            } else {
                // Vytvoříme novou kategorii
                $categoryId = $this->db->insertCategory([
                    'name' => $category['name'],
                    'parent_id' => $category['parent_id'],
                    'position' => $index,
                ]);
            }

            // Rekurzivní zpracování dětí, pokud existují
            if (!empty($category['children']) && is_array($category['children'])) {
                $this->saveOrUpdateCategoriesTree($category['children'], $categoryId);
            }
        }
    }

    public function categoriesSaveOrUpdateEndpoint($data): void
    {
        if (!$data || !isset($data['children'])) {
            Response::send(400, 'Invalid data', $data);
            return;
        }

        // Aktualizace nebo uložení nového stromu
        $this->saveOrUpdateCategoriesTree($data);

        Response::send(200, 'Categories saved or updated successfully', $data);
    }

}
