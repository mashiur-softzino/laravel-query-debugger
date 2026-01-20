<?php

namespace Mash\LaravelQueryDebugger\Services;

final class SuggestionEngine
{
    /**
     * Generate detailed suggestions for N+1 issues
     */
    public function generateN1Suggestions(array $n1Issues): array
    {
        $suggestions = [];

        foreach ($n1Issues as $issue) {
            $suggestions[] = [
                'type' => 'n+1',
                'severity' => $this->calculateSeverity($issue),
                'location' => $issue['location'],
                'problem' => $this->formatProblemDescription($issue),
                'suggestion' => $issue['suggestion'],
                'code_example' => $this->generateCodeExample($issue),
                'impact' => $this->estimateImpact($issue),
                'metrics' => [
                    'query_count' => $issue['count'],
                    'total_time' => $issue['total_time'].'ms',
                    'avg_time' => $issue['avg_time'].'ms',
                ],
            ];
        }

        return $suggestions;
    }

    /**
     * Generate detailed suggestions for slow queries
     */
    public function generateSlowQuerySuggestions(array $slowQueries): array
    {
        $suggestions = [];

        foreach ($slowQueries as $query) {
            $suggestions[] = [
                'type' => 'slow_query',
                'severity' => $query['time'] > 1000 ? 'critical' : 'warning',
                'location' => [
                    'file' => $query['file'] ?? 'unknown',
                    'line' => $query['line'] ?? 0,
                ],
                'problem' => "Slow query detected ({$query['time']}ms)",
                'sql' => $this->formatSql($query['sql']),
                'suggestion' => $this->generateSlowQuerySuggestion($query),
                'possible_solutions' => $this->generatePossibleSolutions($query),
            ];
        }

        return $suggestions;
    }

    /**
     * Generate detailed suggestions for duplicate queries
     */
    public function generateDuplicateSuggestions(array $duplicates): array
    {
        $suggestions = [];

        foreach ($duplicates as $duplicate) {
            $suggestions[] = [
                'type' => 'duplicate',
                'severity' => $duplicate['count'] > 5 ? 'high' : 'medium',
                'problem' => "Exact query runs {$duplicate['count']} times",
                'sql' => $this->formatSql($duplicate['sql']),
                'suggestion' => $duplicate['suggestion'],
                'code_example' => $this->generateCachingExample($duplicate),
                'occurrences' => $duplicate['occurrences'],
                'potential_savings' => round($duplicate['total_time'] * 0.9, 2).'ms', // 90% could be saved
            ];
        }

        return $suggestions;
    }

    /**
     * Calculate severity level based on metrics
     */
    protected function calculateSeverity(array $issue): string
    {
        $count = $issue['count'];
        $totalTime = $issue['total_time'];

        if ($count >= 20 || $totalTime > 2000) {
            return 'critical';
        }

        if ($count >= 10 || $totalTime > 1000) {
            return 'high';
        }

        if ($count >= 5 || $totalTime > 500) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Format problem description
     */
    protected function formatProblemDescription(array $issue): string
    {
        return "N+1 Query: {$issue['count']} queries for '{$issue['table']}' table ({$issue['total_time']}ms total)";
    }

    /**
     * Generate code example for fixing N+1
     */
    protected function generateCodeExample(array $issue): array
    {
        // Use the relationship name if available, otherwise fall back to table name
        $relation = $issue['relationship'] ?? $issue['table'] ?? 'relation';

        // Try to infer parent model from the query location
        $parentModel = $this->guessParentModel($issue);

        return [
            'before' => $this->generateBeforeCode($relation, $parentModel),
            'after' => $this->generateAfterCode($relation, $parentModel),
            'explanation' => "Use eager loading to load {$relation} in advance, reducing {$issue['count']} queries to 2 queries.",
        ];
    }

    /**
     * Guess the parent model from the issue context
     */
    protected function guessParentModel(array $issue): string
    {
        // Look at the query pattern and location to guess the parent model
        $location = $issue['location']['class'] ?? '';

        // If we can detect the model from the location, use it
        if (str_contains($location, 'App\\Models\\')) {
            $modelName = class_basename($location);
            return $modelName;
        }

        // Default fallback
        return 'User';
    }

    /**
     * Generate "before" code example
     */
    protected function generateBeforeCode(string $relation, string $parentModel = 'User'): string
    {
        return <<<PHP
// ❌ Bad: N+1 Problem
\${$this->pluralize($parentModel)} = {$parentModel}::all();
foreach (\${$this->pluralize($parentModel)} as \${$this->singularize($parentModel)}) {
    echo \${$this->singularize($parentModel)}->{$relation}; // Queries {$relation} for each {$this->singularize($parentModel)}
}
PHP;
    }

    /**
     * Generate "after" code example
     */
    protected function generateAfterCode(string $relation, string $parentModel = 'User'): string
    {
        return <<<PHP
// ✅ Good: Eager Loading
\${$this->pluralize($parentModel)} = {$parentModel}::with('{$relation}')->get();
foreach (\${$this->pluralize($parentModel)} as \${$this->singularize($parentModel)}) {
    echo \${$this->singularize($parentModel)}->{$relation}; // No additional queries
}
PHP;
    }

    /**
     * Simple pluralize helper
     */
    protected function pluralize(string $word): string
    {
        $word = strtolower($word);

        // Simple rules for common cases
        if (str_ends_with($word, 's')) {
            return $word;
        }

        return $word . 's';
    }

    /**
     * Simple singularize helper
     */
    protected function singularize(string $word): string
    {
        $word = strtolower($word);

        // Simple rules for common cases
        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Estimate performance impact
     */
    protected function estimateImpact(array $issue): array
    {
        $queriesBefore = $issue['count'] + 1; // N queries + 1 parent
        $queriesAfter = 2; // 1 parent + 1 eager load
        $reduction = $queriesBefore - $queriesAfter;
        $percentReduction = round(($reduction / $queriesBefore) * 100);

        // Estimate time savings (not exact, but gives an idea)
        $estimatedSavings = round($issue['total_time'] * 0.7, 2); // ~70% improvement typically

        return [
            'queries_before' => $queriesBefore,
            'queries_after' => $queriesAfter,
            'query_reduction' => $reduction,
            'percent_reduction' => $percentReduction.'%',
            'estimated_time_savings' => $estimatedSavings.'ms',
        ];
    }

    /**
     * Generate suggestions for slow queries
     */
    protected function generateSlowQuerySuggestion(array $query): string
    {
        $sql = strtolower($query['sql']);
        $suggestions = [];

        // Check for LIKE with leading wildcard
        if (preg_match("/like\s+['\"]%/i", $sql)) {
            $suggestions[] = 'Using LIKE with leading % prevents index usage. Consider full-text search or different search strategy.';
        }

        // Check for SELECT *
        if (str_contains($sql, 'select *')) {
            $suggestions[] = 'SELECT * retrieves all columns. Select only needed columns to improve performance.';
        }

        // Check for missing WHERE clause on large tables
        if (! str_contains($sql, 'where') && ! str_contains($sql, 'limit')) {
            $suggestions[] = 'No WHERE clause detected. This might scan the entire table. Add filtering or pagination.';
        }

        // Check for OR in WHERE clause
        if (preg_match('/where.*\bor\b/i', $sql)) {
            $suggestions[] = 'OR conditions can prevent index usage. Consider using UNION or IN clause instead.';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Consider adding indexes on columns used in WHERE, JOIN, and ORDER BY clauses.';
        }

        return implode(' ', $suggestions);
    }

    /**
     * Generate possible solutions for slow queries
     */
    protected function generatePossibleSolutions(array $query): array
    {
        return [
            'Add database indexes on frequently queried columns',
            'Use select() to specify only needed columns instead of SELECT *',
            'Add pagination with limit/offset for large datasets',
            'Consider caching query results if data doesn\'t change frequently',
            'Review and optimize JOINs - use EXPLAIN to analyze query execution',
        ];
    }

    /**
     * Generate caching example for duplicate queries
     */
    protected function generateCachingExample(array $duplicate): array
    {
        return [
            'before' => <<<'PHP'
// ❌ Query runs multiple times
$users = User::where('active', true)->get();
// ... later in code ...
$users = User::where('active', true)->get(); // Same query again
PHP,
            'after' => <<<'PHP'
// ✅ Cache the result
$users = Cache::remember('active_users', 3600, function () {
    return User::where('active', true)->get();
});
PHP,
            'explanation' => 'Cache query results to avoid redundant database hits.',
        ];
    }

    /**
     * Format SQL for better readability
     */
    protected function formatSql(string $sql): string
    {
        $maxLength = config('query-debugger.display.truncate_length', 100);

        if (config('query-debugger.display.truncate_sql', true) && strlen($sql) > $maxLength) {
            return substr($sql, 0, $maxLength).'...';
        }

        return $sql;
    }
}
