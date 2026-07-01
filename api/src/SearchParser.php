<?php
namespace App;

class SearchParser
{
    /**
     * Minimum term length that gets an automatic trailing wildcard for prefix-fuzzy matching.
     * Shorter terms are kept exact to avoid over-broad matches (e.g. "a*" matching everything).
     */
    private const AUTO_WILDCARD_MIN_LENGTH = 3;

    /**
     * Parse a user-facing search query into a MySQL FULLTEXT BOOLEAN MODE query,
     * plus the plain terms (for LIKE fallback on non-fulltext tables and for the
     * Levenshtein-based fuzzy fallback when BOOLEAN MODE finds nothing).
     *
     * Supported syntax:
     *   foo bar          -> optional terms (natural OR ranking), each gets an auto prefix wildcard
     *   foo AND bar       -> both required
     *   foo OR bar        -> either (same as default, kept for readability)
     *   -foo / NOT foo    -> excludes foo
     *   "exact phrase"    -> exact phrase match, no wildcard
     *   foo*              -> explicit wildcard, kept as-is
     *
     * @param string|null $query
     * @return array{boolean_query: string, terms: array<string>}|null Null if there is nothing to search for
     */
    public static function parse(?string $query): ?array
    {
        if ($query === null || trim($query) === '') {
            return null;
        }

        preg_match_all('/"[^"]*"|\S+/u', trim($query), $matches);

        $booleanParts = [];
        $terms = [];
        $pendingRequire = false;
        $pendingExclude = false;
        $lastPlainPartIndex = null;

        foreach ($matches[0] as $token) {
            $upper = strtoupper($token);
            if ($upper === 'AND') {
                $pendingRequire = true;
                // "A AND B" means both are required, not just B - retroactively
                // require the term seen just before this AND too.
                if ($lastPlainPartIndex !== null && !str_starts_with($booleanParts[$lastPlainPartIndex], '+')) {
                    $booleanParts[$lastPlainPartIndex] = '+' . $booleanParts[$lastPlainPartIndex];
                }
                continue;
            }
            if ($upper === 'OR') {
                continue;
            }
            if ($upper === 'NOT') {
                $pendingExclude = true;
                continue;
            }

            if ($token[0] === '"') {
                $bare = trim($token, '"');
                if ($bare === '') {
                    $pendingRequire = $pendingExclude = false;
                    continue;
                }
                $booleanParts[] = ($pendingExclude ? '-' : ($pendingRequire ? '+' : '')) . '"' . $bare . '"';
                if (!$pendingExclude) {
                    $terms[] = $bare;
                    $lastPlainPartIndex = count($booleanParts) - 1;
                }
                $pendingRequire = $pendingExclude = false;
                continue;
            }

            $exclude = $pendingExclude;
            $require = $pendingRequire;
            $term = $token;

            if ($term[0] === '-') {
                $exclude = true;
                $term = substr($term, 1);
            } elseif ($term[0] === '+') {
                $require = true;
                $term = substr($term, 1);
            }

            // Strip anything that isn't a word character or a trailing wildcard,
            // so a term can never break out of the BOOLEAN MODE query syntax.
            $term = preg_replace('/[^a-zA-Z0-9_*]/u', '', $term);
            $hasWildcard = str_ends_with($term, '*');
            $bareTerm = $hasWildcard ? rtrim($term, '*') : $term;

            if ($bareTerm === '') {
                $pendingRequire = $pendingExclude = false;
                continue;
            }

            if (!$exclude && !$hasWildcard && mb_strlen($bareTerm) >= self::AUTO_WILDCARD_MIN_LENGTH) {
                $hasWildcard = true;
            }

            if ($exclude) {
                $booleanParts[] = '-' . $bareTerm;
            } else {
                $booleanParts[] = ($require ? '+' : '') . $bareTerm . ($hasWildcard ? '*' : '');
                $terms[] = $bareTerm;
                $lastPlainPartIndex = count($booleanParts) - 1;
            }

            $pendingRequire = $pendingExclude = false;
        }

        if (empty($booleanParts)) {
            return null;
        }

        return [
            'boolean_query' => implode(' ', $booleanParts),
            'terms' => array_values(array_unique($terms)),
        ];
    }
}
