<?php
namespace App;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private $config;
    private Database $db;
    private Logger $logger;

    public function __construct($config, Database $db, Logger $logger)
    {
        $this->config = $config;
        $this->db = $db;
        $this->logger = $logger;
    }

    // Funkce pro ověření hesla
    public function verifyPassword($email, $submittedPassword)
    {
        $storedHashedPassword = $this->db->getHashedPassword($email);

        if ($storedHashedPassword) {
            // Porovnání odeslaného hesla (hashu) s uloženým hashem
            if (password_verify($submittedPassword, $storedHashedPassword)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    // Vytvoření JWT tokenu
    public function generateToken($user)
    {
        $payload = [
            'iss' => "localhost", // Vydavatel tokenu
            'sub' => $user['id'], // Uživatelské ID
            'iat' => time(), // Vydáno
            'exp' => time() + (365 * 24 * 60 * 60), // Platnost (1 hodina),
            'user' => [
                'id' => $user['id'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
            ],
        ];
        return JWT::encode($payload, $this->config['jwt_secret'], 'HS256');
    }

    // Ověření JWT tokenu
    public function verifyToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->config['jwt_secret'], 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

// Ověření hlavičky Authorization
public function authenticate()
{
    $headers = apache_request_headers();

    // Kontrola obou variant hlavičky
    $authorizationHeader = null;
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authorizationHeader = $value;
            break;
        }
    }

    if (!$authorizationHeader) {
        return false;
    }

    // Získání tokenu z hlavičky
    $token = str_replace('Bearer ', '', $authorizationHeader);
    return $this->verifyToken($token);
}


    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
