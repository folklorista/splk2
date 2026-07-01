<?php
namespace App;

class SortParser
{
    /**
     * Parse a JSON:API-style ?sort=col1,-col2,+col3 parameter into sort specs.
     * A leading '-' means DESC, a leading '+' (or nothing) means ASC.
     *
     * @param string|null $sortParam
     * @return array|null List of ['column' => string, 'direction' => 'ASC'|'DESC'], or null if not specified
     * @throws \InvalidArgumentException If a column name is invalid
     */
    public static function parse(?string $sortParam): ?array
    {
        if (!$sortParam) {
            return null;
        }

        $tokens = array_filter(array_map('trim', explode(',', $sortParam)), fn($t) => $t !== '');

        if (empty($tokens)) {
            return null;
        }

        $sort = [];
        foreach ($tokens as $token) {
            $direction = 'ASC';
            if ($token[0] === '-') {
                $direction = 'DESC';
                $token = substr($token, 1);
            } elseif ($token[0] === '+') {
                $token = substr($token, 1);
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token)) {
                throw new \InvalidArgumentException("Invalid sort column: '$token'. Column names must be alphanumeric.");
            }

            $sort[] = ['column' => $token, 'direction' => $direction];
        }

        return $sort;
    }
}
