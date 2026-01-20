<?php

namespace Mash\LaravelQueryDebugger\Services;

final class QueryLogger
{
    /**
     * Store all logged queries
     */
    protected array $queries = [];

    /**
     * Track if we've reached the max query limit
     */
    protected bool $limitReached = false;

    /**
     * Log a database query
     */
    public function log(array $queryData): void
    {
        // Check if we've reached the maximum query limit
        $maxQueries = config('query-debugger.max_queries');
        if ($maxQueries && count($this->queries) >= $maxQueries) {
            if (! $this->limitReached) {
                $this->limitReached = true;
                // Log a warning that we've stopped logging
                logger()->warning('Query Debugger: Maximum query limit reached. No more queries will be logged.');
            }

            return;
        }

        // If context was passed, use it; otherwise extract basic file/line for backwards compatibility
        if (isset($queryData['context']) && is_array($queryData['context'])) {
            $queryData['file'] = $queryData['context']['file'];
            $queryData['line'] = $queryData['context']['line'];
            $queryData['class'] = $queryData['context']['class'] ?? null;
            $queryData['method'] = $queryData['context']['method'] ?? null;
            $queryData['location'] = $queryData['context']['location'] ?? '';
            // Remove the nested context array to keep data flat
            unset($queryData['context']);
        }

        // Add timestamp
        $queryData['timestamp'] = microtime(true);

        // Store the query
        $this->queries[] = $queryData;
    }

    /**
     * Get all logged queries
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get queries that exceed the slow threshold
     */
    public function getSlowQueries(int|float|null $threshold = null): array
    {
        $threshold = $threshold ?? config('query-debugger.slow_threshold', 500);

        return array_filter($this->queries, function ($query) use ($threshold) {
            return $query['time'] > $threshold;
        });
    }

    /**
     * Get queries that exceed the critical threshold
     */
    public function getCriticalQueries(): array
    {
        $threshold = config('query-debugger.critical_threshold', 1000);

        return array_filter($this->queries, function ($query) use ($threshold) {
            return $query['time'] > $threshold;
        });
    }

    /**
     * Get total query count
     */
    public function getTotalQueries(): int
    {
        return count($this->queries);
    }

    /**
     * Get total execution time of all queries
     */
    public function getTotalTime(): float
    {
        return array_sum(array_column($this->queries, 'time'));
    }

    /**
     * Clear all logged queries
     * Useful when starting a new request or test
     */
    public function clear(): void
    {
        $this->queries = [];
        $this->limitReached = false;
    }

    /**
     * Get statistics about logged queries
     */
    public function getStatistics(): array
    {
        $totalQueries = $this->getTotalQueries();

        if ($totalQueries === 0) {
            return [
                'total_queries' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'slow_queries' => 0,
                'critical_queries' => 0,
            ];
        }

        $totalTime = $this->getTotalTime();

        return [
            'total_queries' => $totalQueries,
            'total_time' => round($totalTime, 2),
            'avg_time' => round($totalTime / $totalQueries, 2),
            'slow_queries' => count($this->getSlowQueries()),
            'critical_queries' => count($this->getCriticalQueries()),
            'fastest_query' => round(min(array_column($this->queries, 'time')), 2),
            'slowest_query' => round(max(array_column($this->queries, 'time')), 2),
        ];
    }

    /**
     * Get the file and line number where the query originated
     * Uses debug_backtrace to find the caller
     */
    protected function getQueryContext(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        // Filter out framework and package files to find the actual application code
        foreach ($backtrace as $trace) {
            if (! isset($trace['file'])) {
                continue;
            }

            $file = $trace['file'];

            // Skip vendor files (framework, packages)
            if (str_contains($file, '/vendor/')) {
                continue;
            }

            // Skip this package's own files
            if (str_contains($file, 'LaravelQueryDebugger')) {
                continue;
            }

            // Found the application code
            return [
                'file' => $this->getRelativePath($file),
                'line' => $trace['line'] ?? 0,
            ];
        }

        // Fallback if we couldn't find application code
        return [
            'file' => 'unknown',
            'line' => 0,
        ];
    }

    /**
     * Convert absolute path to relative path (from project root)
     * Makes output cleaner and more portable
     */
    protected function getRelativePath(string $absolutePath): string
    {
        $basePath = base_path();

        if (str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath) + 1);
        }

        return $absolutePath;
    }
}
