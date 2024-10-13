<?php
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private $secret;

    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    // Vytvoření JWT tokenu
    public function generateToken($user)
    {
        $payload = [
            'iss' => "localhost",   // Vydavatel tokenu
            'sub' => $user['id'],   // Uživatelské ID
            'iat' => time(),        // Vydáno
            'exp' => time() + (/*365 * 24 * */60 * 60), // Platnost (1 hodina),
            'user' => [
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
            ],
        ];
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    // Ověření JWT tokenu
    public function verifyToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    // Ověření hlavičky Authorization
    public function authenticate()
    {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            return false;
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        return $this->verifyToken($token);
    }
}
