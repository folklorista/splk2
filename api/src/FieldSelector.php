<?php
namespace App;

class FieldSelector
{
    /**
     * Fields that are always considered sensitive and cannot be exposed
     * regardless of configuration - typically crypto keys and tokens
     */
    private static array $ALWAYS_PROTECTED_FIELDS = [
        'api_secret',
        'api_key',
        'jwt_secret',
        'private_key',
    ];

    /**
     * Per-table sensitive fields that should not be exposed by default
     * Users must explicitly request these fields
     */
    private static array $SENSITIVE_BY_TABLE = [
        'users' => ['password', 'password_hash'],
        'api_keys' => ['secret', 'api_secret'],
        'refresh_tokens' => ['token_hash'],
        'password_reset_tokens' => ['token_hash'],
    ];

    /**
     * Parse and validate field selection from query parameter
     *
     * @param string|null $fieldsParam The ?fields=id,name,email parameter
     * @return array|null Array of field names or null if no fields specified
     * @throws \InvalidArgumentException If field list is invalid
     */
    public static function parseFields(?string $fieldsParam): ?array
    {
        if (!$fieldsParam) {
            return null;
        }

        // Remove whitespace and split by comma
        $fields = array_map('trim', explode(',', $fieldsParam));

        // Filter out empty strings
        $fields = array_filter($fields, fn($f) => !empty($f));

        if (empty($fields)) {
            return null;
        }

        // Validate field names (alphanumeric and underscore only)
        foreach ($fields as $field) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                throw new \InvalidArgumentException("Invalid field name: '$field'. Field names must be alphanumeric.");
            }
        }

        return array_values($fields);
    }

    /**
     * Check if a field is always protected
     *
     * @param string $fieldName
     * @return bool
     */
    public static function isAlwaysProtected(string $fieldName): bool
    {
        return in_array(strtolower($fieldName), self::$ALWAYS_PROTECTED_FIELDS, true);
    }

    /**
     * Check if a field is sensitive for a specific table
     *
     * @param string $tableName
     * @param string $fieldName
     * @return bool
     */
    public static function isSensitiveForTable(string $tableName, string $fieldName): bool
    {
        $sensitiveFields = self::$SENSITIVE_BY_TABLE[strtolower($tableName)] ?? [];
        return in_array(strtolower($fieldName), array_map('strtolower', $sensitiveFields), true);
    }

    /**
     * Check if a field should be filtered based on sensitivity and user request
     * A field is returned if:
     * - It's not always protected AND
     * - (It's not sensitive OR it was explicitly requested)
     *
     * @param string $tableName
     * @param string $fieldName
     * @param array|null $requestedFields Null = return all non-sensitive, array = return only requested
     * @return bool
     */
    public static function shouldIncludeField(string $tableName, string $fieldName, ?array $requestedFields): bool
    {
        // Always protected fields are never returned
        if (self::isAlwaysProtected($fieldName)) {
            return false;
        }

        // If no fields specified, return all non-sensitive fields
        if ($requestedFields === null) {
            return !self::isSensitiveForTable($tableName, $fieldName);
        }

        // If fields specified, return only requested fields (unless sensitive and not explicitly requested)
        $fieldInRequest = in_array(strtolower($fieldName), array_map('strtolower', $requestedFields), true);

        // If field is in the request
        if ($fieldInRequest) {
            // Allow it if it's not always protected (already checked above)
            // Allow sensitive fields if explicitly requested (trust the user knows what they're asking for)
            return true;
        }

        return false;
    }

    /**
     * Filter a single record to include only specified/allowed fields
     *
     * @param array $record The database record
     * @param string $tableName Name of the table
     * @param array|null $requestedFields Fields to include or null for defaults
     * @return array Filtered record
     */
    public static function filterRecord(array $record, string $tableName, ?array $requestedFields = null): array
    {
        $filtered = [];

        foreach ($record as $fieldName => $fieldValue) {
            if (self::shouldIncludeField($tableName, $fieldName, $requestedFields)) {
                $filtered[$fieldName] = $fieldValue;
            }
        }

        return $filtered;
    }

    /**
     * Filter an array of records
     *
     * @param array $records Array of database records
     * @param string $tableName Name of the table
     * @param array|null $requestedFields Fields to include or null for defaults
     * @return array Array of filtered records
     */
    public static function filterRecords(array $records, string $tableName, ?array $requestedFields = null): array
    {
        return array_map(
            fn($record) => self::filterRecord($record, $tableName, $requestedFields),
            $records
        );
    }

    /**
     * Get info about which fields were filtered out (for debugging/transparency)
     *
     * @param array $originalRecord
     * @param array $filteredRecord
     * @return array List of filtered field names
     */
    public static function getFilteredFields(array $originalRecord, array $filteredRecord): array
    {
        return array_diff(array_keys($originalRecord), array_keys($filteredRecord));
    }

    /**
     * Get default visible fields for a table (non-sensitive fields)
     *
     * @param string $tableName
     * @param array $record Sample record to extract field names
     * @return array
     */
    public static function getDefaultVisibleFields(string $tableName, array $record): array
    {
        return array_filter(
            array_keys($record),
            fn($field) => self::shouldIncludeField($tableName, $field, null)
        );
    }
}
