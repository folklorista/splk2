<?php
namespace App;

/**
 * Pagination Manager - Handles pagination for API responses
 *
 * Enforces safe pagination limits to prevent:
 * - Server overload (users requesting millions of records)
 * - Memory exhaustion (client receiving too much data)
 * - Network timeouts (large transfers)
 *
 * Features:
 * - Default limit of 100 records
 * - Maximum limit of 1000 records
 * - Offset-based pagination
 * - Returns metadata about total records and pagination state
 */
class PaginationManager
{
    /**
     * Default number of records per page
     */
    public const DEFAULT_LIMIT = 100;

    /**
     * Maximum number of records allowed per request
     */
    public const MAX_LIMIT = 1000;

    /**
     * Minimum number of records per page
     */
    public const MIN_LIMIT = 1;

    /**
     * Maximum offset value allowed (prevents overflow attacks)
     */
    public const MAX_OFFSET = 999999999;

    /**
     * Parse pagination parameters from query string
     *
     * @param int|null $limit Records per page (from query parameter)
     * @param int|null $offset Starting position (from query parameter)
     * @return array{limit: int, offset: int} Validated pagination parameters
     */
    public static function parse(?int $limit = null, ?int $offset = null): array
    {
        // Validate and apply limits
        $limit = self::validateLimit($limit);
        $offset = self::validateOffset($offset);

        return [
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Validate and enforce limit constraints
     *
     * @param int|null $limit Requested limit
     * @return int Validated limit
     */
    private static function validateLimit(?int $limit): int
    {
        // Use default if not provided
        if ($limit === null || $limit <= 0) {
            return self::DEFAULT_LIMIT;
        }

        // Enforce maximum limit
        if ($limit > self::MAX_LIMIT) {
            return self::MAX_LIMIT;
        }

        // Enforce minimum limit
        if ($limit < self::MIN_LIMIT) {
            return self::MIN_LIMIT;
        }

        return $limit;
    }

    /**
     * Validate and enforce offset constraints
     *
     * @param int|null $offset Requested offset
     * @return int Validated offset
     */
    private static function validateOffset(?int $offset): int
    {
        // Use 0 if not provided
        if ($offset === null || $offset < 0) {
            return 0;
        }

        // Enforce maximum offset
        if ($offset > self::MAX_OFFSET) {
            return self::MAX_OFFSET;
        }

        return $offset;
    }

    /**
     * Build pagination metadata for response
     *
     * @param int $total Total number of records available
     * @param int $limit Records per page
     * @param int $offset Starting position
     * @return array Pagination metadata
     */
    public static function buildMetadata(int $total, int $limit, int $offset): array
    {
        $returned = min($limit, max(0, $total - $offset));

        return [
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'returned' => $returned,
                'has_more' => ($offset + $returned) < $total,
                'page' => (int)floor($offset / $limit) + 1,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get SQL LIMIT and OFFSET clause for database query
     *
     * @param int $limit Records per page
     * @param int $offset Starting position
     * @return string SQL LIMIT clause
     */
    public static function getLimitClause(int $limit, int $offset): string
    {
        return "LIMIT {$limit} OFFSET {$offset}";
    }

    /**
     * Check if pagination is needed
     *
     * When total records <= limit, pagination is not needed
     *
     * @param int $total Total records
     * @param int $limit Records per page
     * @return bool True if pagination is needed
     */
    public static function isNeeded(int $total, int $limit): bool
    {
        return $total > $limit;
    }

    /**
     * Get previous page offset
     *
     * @param int $offset Current offset
     * @param int $limit Records per page
     * @return int|null Previous offset, or null if on first page
     */
    public static function getPreviousOffset(int $offset, int $limit): ?int
    {
        if ($offset <= 0) {
            return null;
        }

        $previous = $offset - $limit;
        return $previous < 0 ? 0 : $previous;
    }

    /**
     * Get next page offset
     *
     * @param int $offset Current offset
     * @param int $limit Records per page
     * @param int $total Total records
     * @return int|null Next offset, or null if on last page
     */
    public static function getNextOffset(int $offset, int $limit, int $total): ?int
    {
        $nextOffset = $offset + $limit;

        if ($nextOffset >= $total) {
            return null;
        }

        return $nextOffset;
    }

    /**
     * Build complete pagination links for API response
     *
     * @param int $offset Current offset
     * @param int $limit Records per page
     * @param int $total Total records
     * @param string $basePath Base URL path for links
     * @return array Links with first, last, previous, next
     */
    public static function buildLinks(int $offset, int $limit, int $total, string $basePath): array
    {
        $links = [
            'first' => $basePath . '?limit=' . $limit . '&offset=0',
        ];

        // Last page
        if ($total > 0) {
            $lastOffset = max(0, (int)floor(($total - 1) / $limit) * $limit);
            $links['last'] = $basePath . '?limit=' . $limit . '&offset=' . $lastOffset;
        }

        // Previous page
        $previousOffset = self::getPreviousOffset($offset, $limit);
        if ($previousOffset !== null) {
            $links['previous'] = $basePath . '?limit=' . $limit . '&offset=' . $previousOffset;
        }

        // Next page
        $nextOffset = self::getNextOffset($offset, $limit, $total);
        if ($nextOffset !== null) {
            $links['next'] = $basePath . '?limit=' . $limit . '&offset=' . $nextOffset;
        }

        return $links;
    }
}
