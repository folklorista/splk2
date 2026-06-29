<?php
namespace App;

class RuleValidator
{
    private array $rules;
    private Logger $logger;
    private Database $db;

    public function __construct(array $rules, Database $db, Logger $logger)
    {
        $this->rules = $rules;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Validate data for CREATE operation
     */
    public function validateCreate(string $table, array $data): array
    {
        if (!isset($this->rules[$table])) {
            return ['valid' => true];
        }

        $rule = $this->rules[$table];
        $errors = [];

        if (isset($rule['validation'])) {
            foreach ($rule['validation'] as $field => $constraints) {
                $errors = array_merge($errors, $this->validateField(
                    $field,
                    $data[$field] ?? null,
                    $constraints,
                    $table
                ));
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }

    /**
     * Validate data for UPDATE operation
     */
    public function validateUpdate(string $table, array $data): array
    {
        if (!isset($this->rules[$table])) {
            return ['valid' => true];
        }

        $rule = $this->rules[$table];
        $errors = [];

        if (isset($rule['validation'])) {
            foreach ($rule['validation'] as $field => $constraints) {
                // For update, only validate if field is present
                if (!isset($data[$field])) {
                    continue;
                }

                $errors = array_merge($errors, $this->validateField(
                    $field,
                    $data[$field],
                    $constraints,
                    $table,
                    false  // not required for update
                ));
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }

    /**
     * Validate a single field against constraints
     */
    private function validateField(
        string $field,
        $value,
        array $constraints,
        string $table,
        bool $isRequired = true
    ): array {
        $errors = [];
        $required = $constraints['required'] ?? false;

        // Required check
        if ($required && ($value === null || $value === '')) {
            $errors[] = "$field is required";
            return $errors;  // Skip other validations if required but missing
        }

        if ($value === null || $value === '') {
            return $errors;  // Skip validation if not provided and not required
        }

        // Type validation
        if (isset($constraints['type'])) {
            if (!$this->validateType($value, $constraints['type'])) {
                $errors[] = "$field must be of type {$constraints['type']}";
                return $errors;
            }
        }

        // Length validation
        if (isset($constraints['minLength']) && strlen((string)$value) < $constraints['minLength']) {
            $errors[] = "$field must be at least {$constraints['minLength']} characters";
        }

        if (isset($constraints['maxLength']) && strlen((string)$value) > $constraints['maxLength']) {
            $errors[] = "$field must be at most {$constraints['maxLength']} characters";
        }

        // Enum validation
        if (isset($constraints['enum']) && !in_array($value, $constraints['enum'])) {
            $errors[] = "$field must be one of: " . implode(', ', $constraints['enum']);
        }

        // Unique validation
        if (isset($constraints['unique']) && $constraints['unique'] === true) {
            if (!$this->isUnique($table, $field, $value)) {
                $errors[] = "$field already exists";
            }
        }

        // Unique with validation (composite unique)
        if (isset($constraints['unique_with'])) {
            $message = "$field must be unique in combination with: " . implode(', ', $constraints['unique_with']);
            $errors[] = $message;  // TODO: Implement composite unique check
        }

        // Min/Max value validation
        if (isset($constraints['min']) && is_numeric($value) && $value < $constraints['min']) {
            $errors[] = "$field must be at least {$constraints['min']}";
        }

        if (isset($constraints['max']) && is_numeric($value) && $value > $constraints['max']) {
            $errors[] = "$field must be at most {$constraints['max']}";
        }

        return $errors;
    }

    /**
     * Validate field type
     */
    private function validateType($value, string $type): bool
    {
        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'integer' => is_numeric($value) && intval($value) == $value,
            'float' => is_numeric($value),
            'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1', true, false]),
            'string' => is_string($value),
            'array' => is_array($value),
            default => true,
        };
    }

    /**
     * Check if value is unique in table
     */
    private function isUnique(string $table, string $field, $value): bool
    {
        try {
            $result = $this->db->getAllWhere($table, "`$field` = :value", [':value' => $value]);
            return empty($result['data']) || (isset($result['status']) && $result['status'] === 404);
        } catch (\Exception $e) {
            $this->logger->error("Unique check failed for $table.$field", ['error' => $e->getMessage()]);
            return true;  // Allow if check fails
        }
    }

    /**
     * Execute a hook for a specific event
     */
    public function executeHook(
        string $table,
        string $hookName,
        ...$args
    ): void {
        if (!isset($this->rules[$table]) || !isset($this->rules[$table]['hooks'][$hookName])) {
            return;  // No hook defined
        }

        $hook = $this->rules[$table]['hooks'][$hookName];

        if (!is_callable($hook)) {
            return;  // Hook not callable
        }

        try {
            call_user_func_array($hook, $args);
        } catch (\Exception $e) {
            // Re-throw with proper context
            throw new RuleException(
                $e->getMessage(),
                $e->getCode() ?: 400,
                $table,
                $hookName
            );
        }
    }

    /**
     * Get all validation rules for a table (for schema introspection)
     */
    public function getTableRules(string $table): ?array
    {
        return $this->rules[$table] ?? null;
    }

    /**
     * Check if table has rules defined
     */
    public function hasRules(string $table): bool
    {
        return isset($this->rules[$table]);
    }
}

/**
 * Exception thrown when rule validation or hook fails
 */
class RuleException extends \Exception
{
    private string $table;
    private string $hook;

    public function __construct(string $message, int $code, string $table, string $hook)
    {
        parent::__construct($message, $code);
        $this->table = $table;
        $this->hook = $hook;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getHook(): string
    {
        return $this->hook;
    }
}
