<?php
namespace App;

/**
 * Safe WHERE Clause Builder for SQL Injection Prevention
 *
 * Builds parameterized WHERE clauses instead of string concatenation.
 * All values are properly bound as prepared statement parameters.
 */
class WhereClauseBuilder
{
    private array $conditions = [];
    private array $params = [];
    private int $paramCounter = 0;

    /**
     * Add an equality condition
     *
     * @param string $column Column name (will be backtick-quoted)
     * @param mixed $value Value (will be parameterized)
     * @param string $operator Comparison operator (=, !=, >, <, >=, <=)
     * @return self
     */
    public function eq(string $column, mixed $value, string $operator = '='): self
    {
        if ($value === null) {
            $this->conditions[] = "`{$column}` IS NULL";
        } else {
            $paramName = $this->getNextParamName();
            $this->conditions[] = "`{$column}` {$operator} {$paramName}";
            $this->params[$paramName] = $value;
        }
        return $this;
    }

    /**
     * Add an IN condition
     *
     * @param string $column Column name
     * @param array $values Values to match
     * @return self
     */
    public function in(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $placeholders = [];
        foreach ($values as $value) {
            $paramName = $this->getNextParamName();
            $placeholders[] = $paramName;
            $this->params[$paramName] = $value;
        }

        $this->conditions[] = "`{$column}` IN (" . implode(',', $placeholders) . ")";
        return $this;
    }

    /**
     * Add a LIKE condition
     *
     * @param string $column Column name
     * @param string $value Value with % wildcards
     * @return self
     */
    public function like(string $column, string $value): self
    {
        $paramName = $this->getNextParamName();
        $this->conditions[] = "`{$column}` LIKE {$paramName}";
        $this->params[$paramName] = $value;
        return $this;
    }

    /**
     * Add a FULLTEXT MATCH condition
     *
     * @param array $columns Columns to search
     * @param string $query Search query
     * @return self
     */
    public function match(array $columns, string $query): self
    {
        if (empty($columns)) {
            return $this;
        }

        $quotedColumns = array_map(fn($col) => "`{$col}`", $columns);
        $paramName = $this->getNextParamName();
        $this->conditions[] = "MATCH(" . implode(',', $quotedColumns) . ") AGAINST ({$paramName} IN BOOLEAN MODE)";
        $this->params[$paramName] = $query;
        return $this;
    }

    /**
     * Add a raw condition (use with caution - must not include user input)
     * Only for internal conditions that don't depend on user input
     *
     * @param string $condition Raw SQL condition
     * @return self
     */
    public function raw(string $condition): self
    {
        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * Get next unique parameter name
     *
     * @return string
     */
    private function getNextParamName(): string
    {
        return ':param_' . (++$this->paramCounter);
    }

    /**
     * Get WHERE clause string (use with getParams())
     *
     * @return string Empty string if no conditions, otherwise "condition1 AND condition2"
     */
    public function build(): string
    {
        return implode(' AND ', $this->conditions);
    }

    /**
     * Get parameters array for PDO execute()
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Get both clause and params together
     *
     * @return array ['clause' => string, 'params' => array]
     */
    public function getBoth(): array
    {
        return [
            'clause' => $this->build(),
            'params' => $this->getParams(),
        ];
    }

    /**
     * Get all conditions for inspection (debugging)
     *
     * @return array
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Check if builder has any conditions
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->conditions);
    }

    /**
     * Get count of conditions
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->conditions);
    }
}
