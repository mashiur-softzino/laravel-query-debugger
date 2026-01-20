<?php

namespace Mash\LaravelQueryDebugger\Services;

final class N1Detector
{
    /**
     * Detect N+1 query problems
     *
     * @param  array  $queries  Array of logged queries
     * @return array Array of detected N+1 issues
     */
    public function detect(array $queries): array
    {
        if (!config('query-debugger.detection.n1', true)) {
            return [];
        }

        $patterns = $this->groupQueriesByPattern($queries);
        $threshold = config('query-debugger.n1_threshold', 5);

        $issues = [];

        foreach ($patterns as $pattern => $data) {
            // Skip if below threshold
            if ($data['count'] < $threshold) {
                continue;
            }

            // IMPORTANT: Check if this is actually N+1 or just duplicate queries
            // Eager loading queries (with IN clause) are NOT N+1 - they're good!
            if ($this->isEagerLoadingQuery($pattern)) {
                continue; // Skip - this is expected behavior with eager loading
            }

            // Check if this is a relationship access pattern (true N+1)
            // vs just duplicate queries (like User::find() in a loop)
            $isRelationshipPattern = $this->isRelationshipAccess($data['queries']);

            // Only report as N+1 if it's actually a relationship access
            // Otherwise, it will be caught as a duplicate query by QueryIssueDetector
            if (!$isRelationshipPattern) {
                continue;
            }

            // Extract table from raw SQL (not normalized pattern) to preserve table name
            $tableName = $this->extractTableName($data['raw_sql']);
            $relationshipName = $this->guessRelationName($tableName);

            $issues[] = [
                'type' => 'n+1',
                'pattern' => $pattern,
                'count' => $data['count'],
                'total_time' => $data['total_time'],
                'avg_time' => round($data['total_time'] / $data['count'], 2),
                'queries' => $data['queries'],
                'location' => $this->getMostCommonLocation($data['queries']),
                'table' => $tableName,
                'relationship' => $relationshipName,
                'suggestion' => $tableName
                    ? "Add eager loading: use ->with('{$relationshipName}') on your parent query."
                    : 'Consider using eager loading to reduce the number of queries.',
            ];
        }

        return $issues;
    }

    /**
     * Group queries by their normalized pattern
     * Patterns are SQL statements with values replaced by placeholders
     */
    protected function groupQueriesByPattern(array $queries): array
    {
        $patterns = [];

        foreach ($queries as $query) {
            $pattern = $this->normalizeQuery($query['sql']);

            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = [
                    'count' => 0,
                    'total_time' => 0,
                    'queries' => [],
                    'raw_sql' => $query['sql'], // Store original SQL for table extraction
                ];
            }

            $patterns[$pattern]['count']++;
            $patterns[$pattern]['total_time'] += $query['time'];
            $patterns[$pattern]['queries'][] = $query;
        }


        return $patterns;
    }

    /**
     * Normalize a SQL query by replacing specific values with placeholders
     * This helps us identify queries with the same structure but different values
     *
     * Example:
     * "select * from users where id = 1" -> "select * from users where id = ?"
     * "select * from users where id = 2" -> "select * from users where id = ?"
     */
    protected function normalizeQuery(string $sql): string
    {
        // Convert to lowercase for consistency
        $sql = strtolower($sql);

        // Replace numeric values with ?
        $sql = preg_replace('/\b\d+\b/', '?', $sql);

        // Replace quoted strings with ?
        $sql = preg_replace("/'[^']*'/", '?', $sql);
        $sql = preg_replace('/"[^"]*"/', '?', $sql);

        // Replace multiple spaces with single space
        $sql = preg_replace('/\s+/', ' ', $sql);

        // Trim
        $sql = trim($sql);

        return $sql;
    }

    /**
     * Find the most common file/line location from a set of queries
     * This helps identify where the N+1 is happening
     */
    protected function getMostCommonLocation(array $queries): array
    {
        $locations = [];

        foreach ($queries as $query) {
            if (!isset($query['file']) || !isset($query['line'])) {
                continue;
            }

            $key = $query['file'] . ':' . $query['line'];

            if (!isset($locations[$key])) {
                $locations[$key] = [
                    'file' => $query['file'],
                    'line' => $query['line'],
                    'class' => $query['class'] ?? null,
                    'method' => $query['method'] ?? null,
                    'location' => $query['location'] ?? '',
                    'count' => 0,
                ];
            }

            $locations[$key]['count']++;
        }

        if (empty($locations)) {
            return [
                'file' => 'unknown',
                'line' => 0,
                'class' => null,
                'method' => null,
                'location' => 'unknown',
            ];
        }

        // Sort by count and return the most common location
        usort($locations, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'file' => $locations[0]['file'],
            'line' => $locations[0]['line'],
            'class' => $locations[0]['class'],
            'method' => $locations[0]['method'],
            'location' => $locations[0]['location'],
        ];
    }

    /**
     * Extract table name from SQL query
     * Helps generate better suggestions
     */
    protected function extractTableName(string $sql): ?string
    {
        // Handle SQLite, MySQL, PostgreSQL quoted table names
        // SQLite: "posts", MySQL: `posts`, PostgreSQL: "posts"

        // Try to extract table name from FROM clause
        // Matches: FROM "posts", FROM `posts`, FROM posts
        if (preg_match('/from\s+[`"]?(\w+)[`"]?/i', $sql, $matches)) {
            return $matches[1];
        }

        // Try INSERT INTO
        if (preg_match('/into\s+[`"]?(\w+)[`"]?/i', $sql, $matches)) {
            return $matches[1];
        }

        // Try UPDATE
        if (preg_match('/update\s+[`"]?(\w+)[`"]?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if a query pattern is an eager loading query
     * Eager loading uses WHERE IN clause and is actually GOOD, not N+1
     */
    protected function isEagerLoadingQuery(string $pattern): bool
    {
        // Eager loading queries use WHERE IN (?, ?, ?) pattern
        // Example: "select * from posts where posts.user_id in (?, ?, ?, ?, ?)"
        return str_contains($pattern, ' in (');
    }

    /**
     * Check if queries are from relationship access (true N+1)
     * vs just duplicate queries (like User::find() in a loop)
     *
     * True N+1 happens when accessing relationships in a loop:
     * - $user->posts, $user->profile, etc.
     *
     * NOT N+1 (just duplicates):
     * - User::find($id) repeatedly
     * - Same static query in loop
     */
    protected function isRelationshipAccess(array $queries): bool
    {
        if (empty($queries)) {
            return false;
        }
        // Get the first query to analyze the pattern
        $firstQuery = $queries[0];
        $sql = strtolower($firstQuery['sql']);

        // Check stack trace for relationship access patterns
        // In Laravel, relationship access triggers queries through:
        // - Model::getRelationValue()
        // - Model::__get() (dynamic property access like $user->posts)
        // - Illuminate\Database\Eloquent\Relations\*
        $class = $firstQuery['class'] ?? '';
        $method = $firstQuery['method'] ?? '';

        // If the query is being triggered by relationship methods
        if (
            str_contains($class, 'Relation') ||
            str_contains($method, 'getRelation') ||
            str_contains($method, '__get')
        ) {
            return true;
        }

        // Additional heuristic: Check SQL pattern
        // Relationship queries typically have WHERE foreign_key = ? pattern
        // and are SELECT queries (not find/first which use LIMIT)

        // Pattern 1: WHERE table.column = ? (relationship pattern)
        // This suggests it's fetching related records
        if (preg_match('/where\s+\w+\.\w+\s*=\s*\?/', $sql) && !str_contains($sql, 'limit')) {
            // Could be relationship, but let's be conservative
            // Only flag as N+1 if we see it many times from same location
            return \count($queries) >= 10;
        }

        // Pattern 2: Single record fetch with LIMIT 1 (like find())
        // This is usually NOT relationship access, just duplicate finds
        if (str_contains($sql, 'limit ?') || str_contains($sql, 'limit 1')) {
            return false;
        }

        // Default: If we're not sure, don't flag as N+1
        // Better to miss some N+1s than create false positives
        return false;
    }

    /**
     * Generate a suggestion for fixing the N+1 issue
     */
    protected function generateSuggestion(string $pattern): string
    {
        $table = $this->extractTableName($pattern);

        if (!$table) {
            return 'Consider using eager loading to reduce the number of queries.';
        }

        // Try to guess the relationship name
        // "posts" table -> probably "posts" relation
        $relation = $this->guessRelationName($table);

        return "Add eager loading: use ->with('{$relation}') on your parent query.";
    }

    /**
     * Guess relationship name from table name
     * "posts" -> "posts"
     * "user_posts" -> "userPosts" or "posts"
     */
    protected function guessRelationName(?string $table): string
    {
        // Handle null table name
        if ($table === null) {
            return 'relation';
        }

        // Remove common prefixes
        $table = preg_replace('/^(st_|app_|tbl_)/', '', $table);

        // Convert to camelCase if it has underscores
        if (str_contains($table, '_')) {
            $parts = explode('_', $table);
            $parts = array_map('ucfirst', $parts);
            $table = lcfirst(implode('', $parts));
        }

        return $table;
    }

    /**
     * Detect duplicate queries (exact same query multiple times)
     * This is different from N+1 - these are literally identical queries
     */
    public function detectDuplicates(array $queries): array
    {
        if (!config('query-debugger.detection.duplicate', true)) {
            return [];
        }

        $duplicates = [];
        $seen = [];

        foreach ($queries as $query) {
            // Create a hash of the query (SQL + bindings)
            $hash = md5($query['sql'] . json_encode($query['bindings'] ?? []));

            if (!isset($seen[$hash])) {
                $seen[$hash] = [
                    'query' => $query,
                    'count' => 0,
                    'occurrences' => [],
                ];
            }

            $seen[$hash]['count']++;
            $seen[$hash]['occurrences'][] = [
                'file' => $query['file'] ?? 'unknown',
                'line' => $query['line'] ?? 0,
                'time' => $query['time'],
            ];
        }

        // Find queries that appear more than once
        foreach ($seen as $hash => $data) {
            if ($data['count'] > 1) {
                $duplicates[] = [
                    'type' => 'duplicate',
                    'sql' => $data['query']['sql'],
                    'bindings' => $data['query']['bindings'] ?? [],
                    'count' => $data['count'],
                    'total_time' => array_sum(array_column($data['occurrences'], 'time')),
                    'occurrences' => $data['occurrences'],
                    'suggestion' => 'This exact query runs multiple times. Consider caching the result or refactoring your code.',
                ];
            }
        }

        return $duplicates;
    }
}
