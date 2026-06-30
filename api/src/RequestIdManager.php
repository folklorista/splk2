<?php
namespace App;

/**
 * Request ID Manager - Manages request correlation IDs
 *
 * Generates unique IDs for each request to enable tracing through logs and audit trails.
 * Supports both generating new IDs and accepting IDs from client headers.
 */
class RequestIdManager
{
    /**
     * Header name for receiving request ID from client
     */
    public const REQUEST_ID_HEADER = 'X-Request-ID';

    /**
     * Prefix for generated request IDs to distinguish them from client-provided ones
     */
    public const ID_PREFIX = 'req_';

    /**
     * Current request ID for this request
     */
    private string $requestId;

    /**
     * Whether this ID was provided by client or generated
     */
    private bool $isClientProvided = false;

    /**
     * Initialize RequestIdManager
     * Automatically detects or generates request ID from headers
     */
    public function __construct()
    {
        $this->requestId = $this->detectOrGenerateId();
    }

    /**
     * Detect request ID from headers or generate new one
     *
     * @return string The request ID
     */
    private function detectOrGenerateId(): string
    {
        // Try to get from common header locations
        $clientId = $this->getFromHeaders();

        if ($clientId && $this->isValidFormat($clientId)) {
            $this->isClientProvided = true;
            return $clientId;
        }

        // Generate new ID if not provided or invalid
        return $this->generateId();
    }

    /**
     * Get request ID from HTTP headers
     *
     * @return string|null The request ID from headers, or null
     */
    private function getFromHeaders(): ?string
    {
        // Check common header locations
        $headers = [
            self::REQUEST_ID_HEADER,
            'HTTP_X_REQUEST_ID',
            'X-Correlation-ID',
            'HTTP_X_CORRELATION_ID',
            'X-Trace-ID',
            'HTTP_X_TRACE_ID',
        ];

        foreach ($headers as $header) {
            if (function_exists('apache_request_headers')) {
                $apacheHeaders = apache_request_headers();
                foreach ($apacheHeaders as $key => $value) {
                    if (strtolower($key) === strtolower($header)) {
                        return trim($value);
                    }
                }
            }

            if (isset($_SERVER[$header])) {
                return trim($_SERVER[$header]);
            }
        }

        return null;
    }

    /**
     * Generate a new unique request ID using UUID v4 format
     *
     * @return string Generated request ID with prefix
     */
    private function generateId(): string
    {
        // Generate UUID v4
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        return self::ID_PREFIX . $uuid;
    }

    /**
     * Validate request ID format
     *
     * @param string $id The ID to validate
     * @return bool True if valid format
     */
    private function isValidFormat(string $id): bool
    {
        // Allow UUIDs with optional prefix
        // Format: [prefix_]xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        $pattern = '/^([a-z0-9_-]+_)?[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
        return preg_match($pattern, $id) === 1 && strlen($id) <= 100;
    }

    /**
     * Get the current request ID
     *
     * @return string The request ID for this request
     */
    public function getId(): string
    {
        return $this->requestId;
    }

    /**
     * Check if this ID was provided by the client
     *
     * @return bool True if client-provided, false if generated
     */
    public function isClientProvided(): bool
    {
        return $this->isClientProvided;
    }

    /**
     * Set request ID in response headers
     *
     * @return void
     */
    public function setResponseHeader(): void
    {
        header(self::REQUEST_ID_HEADER . ': ' . $this->requestId);
    }

    /**
     * Get request ID formatted for logging
     *
     * @return string Formatted request ID for log messages
     */
    public function getLogFormat(): string
    {
        return '[' . $this->requestId . ']';
    }

    /**
     * Get request ID formatted for array context (for structured logging)
     *
     * @return array Request ID as array context
     */
    public function getContextArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'id_type' => $this->isClientProvided ? 'client' : 'generated',
        ];
    }
}
