<?php
// index.php

header(header: 'Content-Type: application/json');
require 'config.php';
require 'Database.php';
require 'Auth.php';

// Načtení konfigurace a inicializace DB a autentizace
$config = require 'config.php';
$db = new Database(config: $config);
$auth = new Auth(secret: $config['jwt_secret']);

// Získání HTTP metody a endpointu
$method = $_SERVER['REQUEST_METHOD'];
$path = explode(separator: '/', string: trim(string: $_SERVER['REQUEST_URI'], characters: '/'));

// Validace endpointu
if (count(value: $path) == 0 || empty($path[0])) {
    http_response_code(response_code: 400);
    echo json_encode(value: ["error" => "Invalid endpoint"]);
    exit;
}

$table = $path[0];
$id = $path[1] ?? null;

if ($table == 'login' && $method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['email']) && isset($data['password'])) {
        $email = $data['email'];
        $password = $data['password'];

        // Ověření uživatelského jména a hesla
        $user = $db->verifyUser('users', $email, $password);

        if ($user) {
            // Generování JWT tokenu pro ověřeného uživatele
            $token = $auth->generateToken($user);
            echo json_encode(['token' => $token]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid credentials"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Username and password required"]);
    }
    exit;
} elseif ($table == 'register' && $method == 'POST') {
    // Registrace uživatele (POST /api/register)
    $data = json_decode(json: file_get_contents(filename: 'php://input'), associative: true);

    // Kontrola, zda uživatel poslal email a password
    if (isset($data['email']) && isset($data['password'])) {
        $email = $data['email'];
        $password = $data['password'];
        $first_name = $data['first_name'];
        $last_name = $data['last_name'];

        // Zkontrolovat, zda uživatelské jméno už neexistuje
        $existingUser = $db->getById('users', $email);
        if ($existingUser) {
            http_response_code(400);
            echo json_encode(["error" => "Email already exists"]);
            exit;
        }

        // Hashování hesla
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Uložení uživatele do databáze
        $newUser = [
            'email' => $email,
            'password' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ];

        $userId = $db->insert('users', $newUser);
        echo json_encode(["message" => "User created", "user_id" => $userId]);
    } else {
        http_response_code(response_code: 400);
        echo json_encode(value: ["error" => "Username and password are required"]);
    }
    exit;
}

// Ověření tokenu pro ostatní endpointy
$user = $auth->authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
// CRUD operace (jen pro přihlášené uživatele)
switch ($method) {
    case 'GET':
        if ($id) {
            $result = $db->getById($table, $id);
            if ($result) {
                http_response_code(200);
                echo json_encode($result);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Record not found"]);
            }
        } else {
            $result = $db->getAll($table);
            echo json_encode($result);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            $insertId = $db->insert($table, $data);
            echo json_encode(['id' => $insertId]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input"]);
        }
        break;

    case 'PUT':
        if ($id) {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                $success = $db->update($table, $id, $data);
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Invalid input"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID is required"]);
        }
        break;

    case 'DELETE':
        if ($id) {
            $success = $db->delete($table, $id);
            echo json_encode(['success' => $success]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID is required"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
