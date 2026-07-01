<?php
namespace App;

class FilterParser
{
    /**
     * Map of accepted operator keywords to their SQL equivalents.
     */
    private const OPERATORS = [
        'eq' => '=',
        'ne' => '!=',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'like' => 'LIKE',
        'in' => 'IN',
    ];

    /**
     * Parse the ?filter[column]=value / ?filter[column][operator]=value query
     * parameter into a validated list of conditions.
     *
     * @param array|null $filterParam The parsed `filter` query parameter (from parse_str)
     * @param array $allowedColumns Actual column names for the target table
     * @return array<array{column: string, operator: string, value: mixed}>
     * @throws \InvalidArgumentException If a column or operator is invalid
     */
    public static function parse(?array $filterParam, array $allowedColumns): array
    {
        if (empty($filterParam)) {
            return [];
        }

        $conditions = [];

        foreach ($filterParam as $column => $spec) {
            if (!in_array($column, $allowedColumns, true)) {
                throw new \InvalidArgumentException("Unknown filter column: '$column'.");
            }

            if (is_array($spec)) {
                foreach ($spec as $opKey => $value) {
                    $conditions[] = self::buildCondition($column, (string)$opKey, $value);
                }
            } else {
                $conditions[] = self::buildCondition($column, 'eq', $spec);
            }
        }

        return $conditions;
    }

    /**
     * Apply parsed conditions onto a WhereClauseBuilder.
     *
     * @param WhereClauseBuilder $builder
     * @param array $conditions Output of self::parse()
     * @return WhereClauseBuilder
     */
    public static function apply(WhereClauseBuilder $builder, array $conditions): WhereClauseBuilder
    {
        foreach ($conditions as $condition) {
            if ($condition['operator'] === 'IN') {
                $builder->in($condition['column'], (array)$condition['value']);
            } elseif ($condition['operator'] === 'LIKE') {
                $builder->like($condition['column'], $condition['value']);
            } else {
                $builder->eq($condition['column'], $condition['value'], $condition['operator']);
            }
        }

        return $builder;
    }

    private static function buildCondition(string $column, string $opKey, mixed $value): array
    {
        $opKey = strtolower($opKey);
        if (!isset(self::OPERATORS[$opKey])) {
            throw new \InvalidArgumentException("Unknown filter operator: '$opKey'.");
        }

        $operator = self::OPERATORS[$opKey];

        if ($operator === 'IN') {
            $value = is_array($value) ? $value : array_map('trim', explode(',', (string)$value));
        } elseif ($operator === 'LIKE') {
            $value = '%' . $value . '%';
        }

        return ['column' => $column, 'operator' => $operator, 'value' => $value];
    }
}
