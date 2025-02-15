<?php
namespace App;

class Response
{
    // Funkce pro přípravu odpovědi jako pole
    public static function prepare(
        int $statusCode, 
        string $message, 
        mixed $data = null, 
        string $error = null, 
        array $meta = []
    ) {
        $response = [
            'status' => $statusCode,
            'message' => $message,
            'data' => $data
        ];
    
        if ($statusCode >= 400 && $error === null) {
            $error = match ($statusCode) {
                400 => "Invalid input",
                401 => "Unauthorized access",
                404 => "Not found",
                405 => "Method not allowed",
                500 => "Internal server error",
                default => "Unknown error",
            };
        }
    
        if ($error !== null) {
            $response['error'] = $error;
        }
    
        // Přidání meta informací, pokud existují
        if (!empty(array_filter($meta))) {
            $response['meta'] = $meta;
        }
    
        return $response;
    }

    // Metoda pro odeslání připravené odpovědi (pole) včetně meta informací o stránkování a řazení
    public static function sendPrepared(array $response)
    {
        return self::send(
            statusCode: $response['status'], 
            message: $response['message'], 
            data: $response['data'], 
            error: $response['error'] ?? null,
            meta: $response['meta'] ?? [],
        );
    }

    // Funkce pro odeslání odpovědi (pokud již máme připravené pole)
    public static function send(
        int $statusCode, 
        string $message, 
        mixed $data = null, 
        string $error = null, 
        array $meta = []
    ) {
        http_response_code($statusCode);
        echo json_encode(self::prepare($statusCode, $message, $data, $error, $meta));
        exit;
    }
}