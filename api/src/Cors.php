<?php
namespace App;

/**
 * CORS (Cross-Origin Resource Sharing) Handler
 *
 * Controls which origins are allowed to access this API.
 * All allowed origins must be explicitly configured via CORS_ALLOWED_ORIGINS env variable.
 * Wildcard (*) is NOT allowed - specify exact domains instead.
 */
class Cors
{
    /**
     * Set CORS headers based on configured allowed origins
     *
     * @return void
     * @throws \Exception If CORS_ALLOWED_ORIGINS is not configured
     */
    public static function setHeaders(): void
    {
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $allowedOrigins = self::getAllowedOrigins();

        // Validate request origin
        if ($requestOrigin && self::isOriginAllowed($requestOrigin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . $requestOrigin);
        } else {
            // Origin not allowed or missing - don't set CORS header
            // Browser will block the request
            if ($requestOrigin) {
                // Optional: log rejected origins for security monitoring
                error_log("CORS: Origin rejected: " . $requestOrigin);
            }
        }

        // Standard CORS headers (always set for allowed origins)
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Search-Query, X-Search-Columns, X-Sort-By, X-Sort-Direction, X-Pagination-Limit, X-Pagination-Offset, X-Request-ID");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // Cache preflight for 24 hours

        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit(0);
        }
    }

    /**
     * Get allowed origins from environment variable
     *
     * @return array List of allowed origins
     * @throws \Exception If CORS_ALLOWED_ORIGINS is not configured
     */
    private static function getAllowedOrigins(): array
    {
        $envOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;

        if (!$envOrigins) {
            throw new \Exception(
                "CORS_ALLOWED_ORIGINS environment variable is not configured. "
                . "Please set it to a comma-separated list of allowed origins "
                . "(e.g., 'http://localhost:3000,https://example.com')"
            );
        }

        // Parse comma-separated list and trim whitespace
        $origins = array_map('trim', explode(',', $envOrigins));

        // Filter out empty strings
        return array_filter($origins, fn($origin) => !empty($origin));
    }

    /**
     * Check if a given origin is in the allowed list
     *
     * Supports:
     * - Exact domain match: https://example.com
     * - Port-specific: http://localhost:3000
     * - Subdomain wildcards: https://*.example.com (matches https://app.example.com)
     *
     * @param string $origin Request origin (from HTTP_ORIGIN header)
     * @param array $allowedOrigins List of allowed origins
     * @return bool True if origin is allowed
     */
    private static function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        // Normalize origin (trim, lowercase scheme and host)
        $origin = strtolower(trim($origin));

        foreach ($allowedOrigins as $allowed) {
            $allowed = strtolower(trim($allowed));

            // Exact match
            if ($origin === $allowed) {
                return true;
            }

            // Subdomain wildcard: https://*.example.com matches https://api.example.com
            if (strpos($allowed, '*.') !== false) {
                $pattern = str_replace('*.', '(?:[a-z0-9-]+\\.)', preg_quote($allowed, '/'));
                $pattern = '/^https?:\\/\\/' . $pattern . '(?::\\d+)?$/';

                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}
