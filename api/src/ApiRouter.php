<?php
namespace App;

/**
 * API Router - Handles API versioning and routing
 *
 * Supports versioned endpoints:
 * - /api/v1/users/123
 * - /api/v2/users/123
 *
 * Also supports legacy format without /api prefix for backwards compatibility:
 * - /users/123 (treated as v1)
 */
class ApiRouter
{
    private const DEFAULT_VERSION = 'v1';
    private const SUPPORTED_VERSIONS = ['v1'];
    private const PLANNED_VERSIONS = ['v2', 'v3'];  // Future versions

    /**
     * Parse the request URI and extract API version and resource path
     *
     * @param string $requestUri The REQUEST_URI from $_SERVER
     * @return array{
     *     version: string,
     *     method: string,
     *     resource: string|null,
     *     id: string|null,
     *     path: array,
     *     pathIndex: array
     * } Routing information
     */
    public static function parseRequest(string $requestUri): array
    {
        $parsedUrl = parse_url($requestUri);
        $path = explode('/', trim($parsedUrl['path'], '/'));

        // Initialize defaults
        $version = self::DEFAULT_VERSION;
        $validPath = $path;
        $method = $_SERVER['REQUEST_METHOD'];

        // Check if this is a versioned endpoint: /api/v1/...
        if (count($path) >= 2 && $path[0] === 'api' && self::isVersionString($path[1])) {
            $version = $path[1];
            // Remove 'api' and version from path
            $validPath = array_slice($path, 2);
        }

        // Validate version is supported
        if (!in_array($version, self::SUPPORTED_VERSIONS)) {
            throw new \Exception("Unsupported API version: $version");
        }

        // Extract resource name and ID from path
        $resource = !empty($validPath[0]) ? $validPath[0] : null;
        $id = !empty($validPath[1]) ? $validPath[1] : null;

        return [
            'version' => $version,
            'method' => $method,
            'resource' => $resource,
            'id' => $id,
            'path' => $validPath,
            'pathIndex' => [
                'table' => 0,  // Resource name is at index 0 in validPath
                'id' => 1,     // ID is at index 1 in validPath
            ],
        ];
    }

    /**
     * Check if a string matches version format (v1, v2, v3, etc)
     *
     * @param string $str
     * @return bool
     */
    private static function isVersionString(string $str): bool
    {
        return preg_match('/^v\d+$/', $str) === 1;
    }

    /**
     * Get all supported API versions
     *
     * @return array
     */
    public static function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

    /**
     * Check if a version is supported
     *
     * @param string $version
     * @return bool
     */
    public static function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS);
    }

    /**
     * Get deprecation info for a version
     * Returns null if not deprecated, or array with deprecation info
     *
     * @param string $version
     * @return array|null
     */
    public static function getDeprecationInfo(string $version): ?array
    {
        // Define deprecation timeline
        $deprecations = [
            // 'v1' => [
            //     'deprecated_at' => '2026-06-30',
            //     'sunset_date' => '2027-06-30',
            //     'message' => 'v1 is deprecated, please migrate to v2'
            // ],
        ];

        return $deprecations[$version] ?? null;
    }
}
