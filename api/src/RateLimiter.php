<?php
namespace App;

/**
 * Rate Limiting Implementation
 *
 * Limits requests per IP address or user ID to prevent abuse and DDoS attacks.
 * Supports role-based rate limiting with different limits for guest/user/admin.
 * Uses file-based persistence.
 */
class RateLimiter
{
    private string $storePath;
    private int $maxRequests;
    private int $windowSeconds;
    private Logger $logger;
    private array $roleLimits = [
        'guest' => 10,      // 10 requests/minute
        'user' => 100,      // 100 requests/minute
        'admin' => 1000,    // 1000 requests/minute
    ];

    public function __construct(
        Logger $logger,
        string $storePath = '',
        int $maxRequests = 100,
        int $windowSeconds = 60
    ) {
        $this->logger = $logger;
        $this->storePath = $storePath ?: sys_get_temp_dir() . '/rate_limit_' . md5(__FILE__);
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;

        // Allow override of role limits from env
        $this->roleLimits['guest'] = (int)($_ENV['RATE_LIMIT_GUEST'] ?? 10);
        $this->roleLimits['user'] = (int)($_ENV['RATE_LIMIT_USER'] ?? 100);
        $this->roleLimits['admin'] = (int)($_ENV['RATE_LIMIT_ADMIN'] ?? 1000);

        // Initialize storage if doesn't exist
        if (!file_exists($this->storePath)) {
            @mkdir($this->storePath, 0755, true);
        }
    }

    /**
     * Get limit based on user role
     */
    public function getLimitForRole(string $role): int
    {
        return $this->roleLimits[$role] ?? $this->maxRequests;
    }

    /**
     * Check if request should be rate limited
     *
     * @param string $identifier IP address or user ID
     * @param string $role User role for role-based limiting (guest, user, admin)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int, 'limit' => int]
     */
    public function checkLimit(string $identifier, string $role = 'guest'): array
    {
        $key = $this->getKey($identifier);
        $data = $this->readData($key);
        $now = time();
        $limit = $this->getLimitForRole($role);

        // Clean up old window
        if ($data['reset_at'] < $now) {
            $data = [
                'count' => 0,
                'reset_at' => $now + $this->windowSeconds,
            ];
        }

        // Increment counter
        $data['count']++;

        // Save updated data
        $this->writeData($key, $data);

        $allowed = $data['count'] <= $limit;
        $remaining = max(0, $limit - $data['count']);

        if (!$allowed) {
            $this->logger->warning("Rate limit exceeded for $identifier (role: $role)", [
                'identifier' => $identifier,
                'role' => $role,
                'count' => $data['count'],
                'limit' => $limit,
            ]);
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_at' => $data['reset_at'],
            'limit' => $limit,
        ];
    }

    /**
     * Get identifier from request (IP address)
     */
    public static function getClientIdentifier(): string
    {
        // Check X-Forwarded-For first (for proxies/load balancers)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        // Fall back to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Read request count data for identifier
     */
    private function readData(string $key): array
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return [
                'count' => 0,
                'reset_at' => time() + $this->windowSeconds,
            ];
        }

        $data = json_decode(file_get_contents($file), true);

        return is_array($data) ? $data : [
            'count' => 0,
            'reset_at' => time() + $this->windowSeconds,
        ];
    }

    /**
     * Write request count data for identifier
     */
    private function writeData(string $key, array $data): void
    {
        $file = $this->getFilePath($key);

        // Create parent directory if needed
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($data), LOCK_EX);

        // Clean old files periodically (every 100 writes)
        if (random_int(1, 100) === 1) {
            $this->cleanupOldFiles();
        }
    }

    /**
     * Get file path for identifier
     */
    private function getFilePath(string $key): string
    {
        // Use hash to avoid filesystem issues with special chars
        $hash = md5($key);
        return $this->storePath . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }

    /**
     * Get normalized key for identifier
     */
    private function getKey(string $identifier): string
    {
        return 'rate_limit:' . $identifier;
    }

    /**
     * Clean up old rate limit files
     */
    private function cleanupOldFiles(): void
    {
        try {
            $now = time();
            $iterator = new \RecursiveDirectoryIterator($this->storePath);
            $recursive = new \RecursiveIteratorIterator($iterator);
            $pattern = '/\.json$/';

            foreach ($recursive as $file) {
                if (preg_match($pattern, $file->getFilename())) {
                    // Delete files older than 2 windows
                    if ($now - filemtime($file) > $this->windowSeconds * 2) {
                        @unlink($file);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail cleanup
        }
    }

    /**
     * Reset limit for identifier (useful for admin operations)
     */
    public function reset(string $identifier): void
    {
        $key = $this->getKey($identifier);
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Get current status for identifier (non-destructive check)
     */
    public function getStatus(string $identifier): array
    {
        $key = $this->getKey($identifier);
        $data = $this->readData($key);
        $now = time();

        $allowanceLeft = max(0, $this->maxRequests - ($data['count'] ?? 0));

        return [
            'requests_made' => $data['count'] ?? 0,
            'requests_remaining' => $allowanceLeft,
            'window_reset_in' => max(0, ($data['reset_at'] ?? $now) - $now),
            'limit_per_window' => $this->maxRequests,
            'window_size_seconds' => $this->windowSeconds,
        ];
    }
}
