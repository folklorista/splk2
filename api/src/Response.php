<?php
namespace App;

class Response
{
    // Funkce pro přípravu odpovědi jako pole
    public static function prepare(int $statusCode, string $message, mixed $data = null, string $error = null)
    {
        $response = [
            'status' => $statusCode,
            'message' => $message,
            'data' => $data,
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

        return $response;
    }

    // Metoda pro odeslání připravené odpovědi (pole)
    public static function sendPrepared(array $response)
    {
        return self::send($response['status'], $response['message'], $response['data'], key_exists('error', $response) ? $response['error']: null);
    }

    // Funkce pro odeslání odpovědi (pokud již máme připravené pole)
    public static function send(int $statusCode, string $message, mixed $data = null, string $error = null)
    {
        http_response_code($statusCode);
        echo json_encode(self::prepare($statusCode, $message, $data, $error));
        exit;
    }
}
