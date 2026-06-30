<?php
namespace App;

class Response
{
    /**
     * Context for setting appropriate cache headers
     * Should be set before sending response
     */
    private static ?string $endpoint = null;
    private static ?string $method = null;
    /**
     * Field selection for sparse fieldsets
     */
    private static ?array $selectedFields = null;

    /**
     * Set field selection for sparse fieldsets
     *
     * @param array|null $fields Array of field names or null for all fields
     */
    public static function setFieldSelection(?array $fields): void
    {
        self::$selectedFields = $fields;
    }

    /**
     * Reset field selection
     */
    public static function resetFieldSelection(): void
    {
        self::$selectedFields = null;
    }

    // Funkce pro přípravu odpovědi jako pole
    public static function prepare(
        int $statusCode,
        string $message,
        mixed $data = null,
        string $error = null,
        array $meta = [],
        ?string $tableName = null
    ) {
        // Apply field selection if data is present and table name is known
        if ($data !== null && $tableName !== null && self::$selectedFields !== null) {
            if (is_array($data)) {
                if (isset($data[0]) && is_array($data[0])) {
                    // Array of records
                    $data = FieldSelector::filterRecords($data, $tableName, self::$selectedFields);
                } elseif (is_array($data) && !empty($data) && !isset($data[0])) {
                    // Single record (associative array)
                    $data = FieldSelector::filterRecord($data, $tableName, self::$selectedFields);
                }
            }
        }

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

    /**
     * Set context for cache headers
     * Call before send() to enable appropriate caching
     *
     * @param string $endpoint The endpoint name (resource)
     * @param string $method HTTP method (GET, POST, etc)
     */
    public static function setContext(string $endpoint, string $method): void
    {
        self::$endpoint = $endpoint;
        self::$method = $method;
    }

    // Funkce pro odeslání odpovědi (pokud již máme připravené pole)
    public static function send(
        int $statusCode,
        string $message,
        mixed $data = null,
        string $error = null,
        array $meta = []
    ) {
        // Prepare response data
        $responseData = self::prepare($statusCode, $message, $data, $error, $meta);
        $responseJson = json_encode($responseData);

        // Set cache headers if endpoint and method are set
        if (self::$endpoint && self::$method) {
            // Check if client has valid cached version
            if ($statusCode === 200) {
                $eTag = CacheHeaderManager::generateETag($responseJson);

                // Check for 304 Not Modified before sending full response
                if (CacheHeaderManager::isClientCacheValid($eTag)) {
                    CacheHeaderManager::sendNotModified();
                }

                // Set cache headers for successful response
                if (CacheHeaderManager::shouldCache(self::$endpoint, self::$method, $statusCode)) {
                    CacheHeaderManager::setCacheHeaders(self::$endpoint, self::$method, $eTag);
                } else {
                    // No-cache for private/user-specific data
                    CacheHeaderManager::setCacheHeaders(self::$endpoint, self::$method);
                }
            }
        }

        http_response_code($statusCode);
        echo $responseJson;
        exit;
    }
}