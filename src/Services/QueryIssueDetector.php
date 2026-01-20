<?php

namespace Mash\LaravelQueryDebugger\Services;

/**
 * Detects various query performance issues beyond N+1
 */
final class QueryIssueDetector
{
    /**
     * Detect duplicate queries (exact same SQL executed multiple times)
     */
    public function detectDuplicates(array $queries): array
    {
        $duplicates = [];
        $queryMap = [];

        foreach ($queries as $query) {
            // Create a unique key for the query (SQL + bindings)
            $key = $this->normalizeQuery($query['sql'], $query['bindings']);

            if (! isset($queryMap[$key])) {
                $queryMap[$key] = [
                    'sql' => $query['sql'],
                    'bindings' => $query['bindings'],
                    'count' => 0,
                    'total_time' => 0,
                    'queries' => [],
                ];
            }

            $queryMap[$key]['count']++;
            $queryMap[$key]['total_time'] += $query['time'];
            $queryMap[$key]['queries'][] = $query;
        }

        // Filter out queries that were executed only once
        foreach ($queryMap as $key => $data) {
            if ($data['count'] > 1) {
                // Get location from first occurrence
                $firstQuery = $data['queries'][0];

                $duplicates[] = [
                    'type' => 'duplicate',
                    'sql' => $data['sql'],
                    'bindings' => $data['bindings'],
                    'count' => $data['count'],
                    'total_time' => $data['total_time'],
                    'avg_time' => $data['total_time'] / $data['count'],
                    'wasted_time' => $data['total_time'] - ($data['total_time'] / $data['count']),
                    'queries' => $data['queries'],
                    'severity' => $this->calculateDuplicateSeverity($data['count'], $data['total_time']),
                    'file' => $firstQuery['file'] ?? 'unknown',
                    'line' => $firstQuery['line'] ?? 0,
                    'class' => $firstQuery['class'] ?? null,
                    'method' => $firstQuery['method'] ?? null,
                    'location' => $firstQuery['location'] ?? '',
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Detect queries executed inside loops (via backtrace analysis)
     */
    public function detectQueriesInLoops(array $queries): array
    {
        $loopQueries = [];

        foreach ($queries as $query) {
            // Check if the query has file/line information
            if (! isset($query['file']) || ! isset($query['line'])) {
                continue;
            }

            // Look for common loop indicators in the call stack
            $isInLoop = $this->isQueryInLoop($query);

            if ($isInLoop) {
                $loopQueries[] = [
                    'type' => 'query_in_loop',
                    'sql' => $query['sql'],
                    'bindings' => $query['bindings'],
                    'time' => $query['time'],
                    'file' => $query['file'],
                    'line' => $query['line'],
                    'class' => $query['class'] ?? null,
                    'method' => $query['method'] ?? null,
                    'location' => $query['location'] ?? '',
                    'severity' => $query['time'] > 10 ? 'high' : 'medium',
                ];
            }
        }

        return $loopQueries;
    }

    /**
     * Detect SELECT * queries
     */
    public function detectSelectAll(array $queries): array
    {
        $selectAllQueries = [];

        foreach ($queries as $query) {
            $sql = strtolower(trim($query['sql']));

            // Check if it's a SELECT * query
            if (preg_match('/^select\s+\*\s+from/i', $sql)) {
                $selectAllQueries[] = [
                    'type' => 'select_all',
                    'sql' => $query['sql'],
                    'time' => $query['time'],
                    'file' => $query['file'] ?? 'unknown',
                    'line' => $query['line'] ?? 0,
                    'class' => $query['class'] ?? null,
                    'method' => $query['method'] ?? null,
                    'location' => $query['location'] ?? '',
                    'severity' => 'low',
                ];
            }
        }

        return $selectAllQueries;
    }

    /**
     * Detect large result sets (queries returning many rows)
     * Note: This requires additional instrumentation to track row counts
     */
    public function detectLargeResultSets(array $queries): array
    {
        // This would require additional tracking of result set sizes
        // For now, we'll detect queries with LIMIT > 500
        $largeQueries = [];

        foreach ($queries as $query) {
            $sql = strtolower($query['sql']);

            // Check for LIMIT clause
            if (preg_match('/limit\s+(\d+)/i', $sql, $matches)) {
                $limit = (int) $matches[1];

                if ($limit > 500) {
                    $largeQueries[] = [
                        'type' => 'large_result_set',
                        'sql' => $query['sql'],
                        'limit' => $limit,
                        'time' => $query['time'],
                        'severity' => $limit > 1000 ? 'high' : 'medium',
                    ];
                }
            }
        }

        return $largeQueries;
    }

    /**
     * Get all issues detected
     */
    public function detectAllIssues(array $queries): array
    {
        return [
            'duplicates' => $this->detectDuplicates($queries),
            'queries_in_loops' => $this->detectQueriesInLoops($queries),
            'select_all' => $this->detectSelectAll($queries),
            'large_result_sets' => $this->detectLargeResultSets($queries),
        ];
    }

    /**
     * Normalize query for comparison (remove variable bindings)
     */
    protected function normalizeQuery(string $sql, array $bindings): string
    {
        // Create a fingerprint of the query
        // Replace all ? with a placeholder to group similar queries
        $normalized = preg_replace('/\s+/', ' ', trim($sql));

        // Include bindings in the hash to differentiate truly identical queries
        return md5($normalized.json_encode($bindings));
    }

    /**
     * Analyze if a query is executed inside a loop
     */
    protected function isQueryInLoop(array $query): bool
    {
        // This is a heuristic - we can improve it
        // Look at the file/line and check if it's being called repeatedly
        // For now, we'll use a simple heuristic based on query timing patterns

        // If we have access to debug_backtrace, we could analyze the call stack
        // But for now, we'll mark this as a placeholder for future enhancement
        return false; // Will be enhanced when we add better stack trace analysis
    }

    /**
     * Calculate severity of duplicate queries
     */
    protected function calculateDuplicateSeverity(int $count, float $totalTime): string
    {
        if ($count > 10 || $totalTime > 100) {
            return 'high';
        }

        if ($count > 5 || $totalTime > 50) {
            return 'medium';
        }

        return 'low';
    }
}
