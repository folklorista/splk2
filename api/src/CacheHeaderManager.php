<?php
namespace App;

/**
 * Cache Header Manager - Manages HTTP cache headers for responses
 *
 * Implements HTTP caching strategy using:
 * - Cache-Control headers (max-age, public/private)
 * - ETag (entity tag) for cache validation
 * - Last-Modified for If-Modified-Since support
 *
 * Benefits:
 * - Reduce bandwidth usage
 * - Improve response times (cached responses)
 * - Reduce server load
 * - Better mobile experience
 */
class CacheHeaderManager
{
    /**
     * Cache strategies by endpoint type
     */
    private const CACHE_STRATEGIES = [
        // Rarely changing data (health, docs, versions)
        'health' => ['max_age' => 300, 'public' => true],           // 5 minutes
        'versions' => ['max_age' => 3600, 'public' => true],        // 1 hour
        'docs' => ['max_age' => 86400, 'public' => true],           // 1 day
        'openapi' => ['max_age' => 86400, 'public' => true],        // 1 day

        // User-specific data (should not be cached publicly)
        'default' => ['max_age' => 0, 'public' => false],            // No cache
    ];

    /**
     * Methods that should be cached
     */
    private const CACHEABLE_METHODS = ['GET', 'HEAD'];

    /**
     * Generate ETag from response data
     *
     * @param string|array $data Response data
     * @return string ETag value (without quotes)
     */
    public static function generateETag($data): string
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        // Use SHA-256 hash for strong ETag
        return hash('sha256', $data);
    }

    /**
     * Get cache strategy for endpoint
     *
     * @param string $endpoint The endpoint name (resource)
     * @param string $method HTTP method
     * @return array Cache strategy with max_age and public flag
     */
    public static function getCacheStrategy(string $endpoint, string $method = 'GET'): array
    {
        // Only cache GET and HEAD requests
        if (!in_array($method, self::CACHEABLE_METHODS)) {
            return self::CACHE_STRATEGIES['default'];
        }

        // Return specific strategy or default
        return self::CACHE_STRATEGIES[$endpoint] ?? self::CACHE_STRATEGIES['default'];
    }

    /**
     * Check if response should be cached
     *
     * @param string $endpoint The endpoint
     * @param string $method HTTP method
     * @param int $statusCode HTTP response status code
     * @return bool True if response should be cached
     */
    public static function shouldCache(string $endpoint, string $method, int $statusCode): bool
    {
        // Only cache successful GET/HEAD responses
        if (!in_array($method, self::CACHEABLE_METHODS)) {
            return false;
        }

        // Only cache 200 and 304 responses
        if (!in_array($statusCode, [200, 304])) {
            return false;
        }

        // Don't cache default (no-cache) endpoints
        $strategy = self::getCacheStrategy($endpoint, $method);
        return $strategy['max_age'] > 0;
    }

    /**
     * Set cache headers in response
     *
     * @param string $endpoint The endpoint name
     * @param string $method HTTP method
     * @param string|null $eTag Optional ETag value
     * @return void
     */
    public static function setCacheHeaders(string $endpoint, string $method, ?string $eTag = null): void
    {
        $strategy = self::getCacheStrategy($endpoint, $method);

        // Build Cache-Control header
        $cacheControl = [];
        if ($strategy['public']) {
            $cacheControl[] = 'public';
        } else {
            $cacheControl[] = 'private';
        }

        $cacheControl[] = 'max-age=' . $strategy['max_age'];

        // Add revalidation hint
        if ($strategy['max_age'] > 0) {
            $cacheControl[] = 'must-revalidate';
        }

        header('Cache-Control: ' . implode(', ', $cacheControl));

        // Set ETag if provided
        if ($eTag) {
            header('ETag: "' . $eTag . '"');
        }

        // Set Last-Modified (current time)
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T'));
    }

    /**
     * Check if client has valid cached version
     *
     * @param string $eTag The current ETag
     * @return bool True if client's cached version is still valid (304 Not Modified)
     */
    public static function isClientCacheValid(string $eTag): bool
    {
        // Check If-None-Match header (ETag validation)
        $clientETag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;

        if ($clientETag) {
            // Remove quotes from client ETag if present
            $clientETag = trim($clientETag, '"');

            return $clientETag === $eTag;
        }

        // Check If-Modified-Since header (basic validation)
        // Note: This is simplified - real implementation would compare timestamps
        $clientModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

        return $clientModified !== null;
    }

    /**
     * Send 304 Not Modified response
     * Used when client's cached version is still valid
     *
     * @return void
     */
    public static function sendNotModified(): void
    {
        http_response_code(304);
        header('Cache-Control: private, max-age=0, must-revalidate');
        exit(0);
    }

    /**
     * Get all cache strategies
     *
     * @return array
     */
    public static function getAllStrategies(): array
    {
        return self::CACHE_STRATEGIES;
    }
}
