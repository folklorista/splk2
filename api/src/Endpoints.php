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

}
