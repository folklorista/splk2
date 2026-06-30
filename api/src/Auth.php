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

    // Vytvoření JWT tokenu (access token - krátkodobý)
    public function generateToken($user)
    {
        $jwtExpiration = (int)($_ENV['JWT_EXPIRATION'] ?? 900); // Default 15 minutes

        $payload = [
            'iss' => "localhost", // Vydavatel tokenu
            'sub' => $user['id'], // Uživatelské ID
            'iat' => time(), // Vydáno
            'exp' => time() + $jwtExpiration,
            'user' => [
                'id' => $user['id'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
            ],
        ];
        return JWT::encode($payload, $this->config['jwt_secret'], 'HS256');
    }

    // Generování refresh tokenu
    public function generateRefreshToken($userId, $expirationDays = 7)
    {
        try {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + ($expirationDays * 24 * 60 * 60));

            $this->db->execute(
                "INSERT INTO refresh_tokens (token_hash, user_id, expires_at) VALUES (:token_hash, :user_id, :expires_at)",
                [
                    ':token_hash' => $tokenHash,
                    ':user_id' => $userId,
                    ':expires_at' => $expiresAt,
                ]
            );

            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Error generating refresh token: ' . $e->getMessage());
            return false;
        }
    }

    // Ověření refresh tokenu a vystavení nového access tokenu
    public function refreshAccessToken($refreshToken)
    {
        if (!$refreshToken) {
            return false;
        }

        try {
            $tokenHash = hash('sha256', $refreshToken);

            $tokenRecord = $this->db->fetchOne(
                "SELECT id, user_id, expires_at, revoked FROM refresh_tokens WHERE token_hash = :token_hash",
                [':token_hash' => $tokenHash]
            );

            if (!$tokenRecord) {
                return false;
            }

            // Kontrola, zda token není odvolán
            if ($tokenRecord['revoked']) {
                return false;
            }

            // Kontrola vypršení tokenu
            $expiresAt = strtotime($tokenRecord['expires_at']);
            if ($expiresAt < time()) {
                return false;
            }

            // Získání uživatele
            $userResult = $this->db->get('users', $tokenRecord['user_id']);
            if (!$userResult || $userResult['status'] !== 200) {
                return false;
            }

            $user = $userResult['data'];

            // Generování nového access tokenu
            $newAccessToken = $this->generateToken($user);

            // Token rotation - odvolat starý refresh token a vystavit nový
            $this->db->execute(
                "UPDATE refresh_tokens SET revoked = 1 WHERE id = :id",
                [':id' => $tokenRecord['id']]
            );

            $newRefreshToken = $this->generateRefreshToken($user['id']);

            return [
                'accessToken' => $newAccessToken,
                'refreshToken' => $newRefreshToken,
                'expiresIn' => (int)($_ENV['JWT_EXPIRATION'] ?? 900),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing token: ' . $e->getMessage());
            return false;
        }
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
    $authorizationHeader = null;

    // Try Apache headers first
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authorizationHeader = $value;
                break;
            }
        }
    }

    // Fallback to $_SERVER (works with PHP development server)
    if (!$authorizationHeader && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
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
