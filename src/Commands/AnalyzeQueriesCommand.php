<?php

namespace Mash\LaravelQueryDebugger\Commands;

use Illuminate\Console\Command;
use Mash\LaravelQueryDebugger\Services\N1Detector;
use Mash\LaravelQueryDebugger\Services\QueryLogger;
use Mash\LaravelQueryDebugger\Services\SuggestionEngine;

class AnalyzeQueriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debug:queries
                            {--slow= : Show queries slower than this threshold in milliseconds (default: config value)}
                            {--all : Show all queries, not just issues}
                            {--export= : Export report to file (json, html)}
                            {--no-suggestions : Hide fix suggestions}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze database queries for N+1 problems and slow queries';

    /**
     * Execute the console command.
     */
    public function handle(
        QueryLogger $logger,
        N1Detector $detector,
        SuggestionEngine $suggestionEngine
    ): int {
        $this->info('🔍 Analyzing Database Queries...');
        $this->newLine();

        // Get all logged queries
        $queries = $logger->getQueries();

        if (empty($queries)) {
            $this->warn('⚠️  No queries have been logged yet.');
            $this->info('Make some requests to your application first, then run this command.');

            return self::SUCCESS;
        }

        // Show statistics
        $this->displayStatistics($logger);

        // Detect N+1 issues
        $n1Issues = $detector->detect($queries);
        if (! empty($n1Issues)) {
            $this->displayN1Issues($n1Issues, $suggestionEngine);
        }

        // Detect duplicate queries
        $duplicates = $detector->detectDuplicates($queries);
        if (! empty($duplicates)) {
            $this->displayDuplicates($duplicates);
        }

        // Show slow queries if requested
        $slowThreshold = $this->option('slow') ?? config('query-debugger.slow_threshold', 500);
        $slowQueries = $logger->getSlowQueries($slowThreshold);

        if (! empty($slowQueries)) {
            $this->displaySlowQueries($slowQueries, $slowThreshold);
        }

        // Show all queries if requested
        if ($this->option('all')) {
            $this->displayAllQueries($queries);
        }

        // Export report if requested
        if ($format = $this->option('export')) {
            $this->exportReport($logger, $n1Issues, $duplicates, $slowQueries, $format);
        }

        // Final summary
        $this->newLine();
        $this->displayFinalSummary($n1Issues, $duplicates, $slowQueries);

        return self::SUCCESS;
    }

    /**
     * Display query statistics
     */
    protected function displayStatistics(QueryLogger $logger): void
    {
        $stats = $logger->getStatistics();

        $this->components->twoColumnDetail('Total Queries', $stats['total_queries']);
        $this->components->twoColumnDetail('Total Time', $stats['total_time'].'ms');
        $this->components->twoColumnDetail('Average Time', $stats['avg_time'].'ms');
        $this->components->twoColumnDetail('Fastest Query', $stats['fastest_query'].'ms');
        $this->components->twoColumnDetail('Slowest Query', $stats['slowest_query'].'ms');
        $this->components->twoColumnDetail('Slow Queries', $stats['slow_queries']);
        $this->components->twoColumnDetail('Critical Queries', $stats['critical_queries']);

        $this->newLine();
    }

    /**
     * Display N+1 issues
     */
    protected function displayN1Issues(array $n1Issues, SuggestionEngine $suggestionEngine): void
    {
        $this->components->error('❌ N+1 Query Problems Detected');
        $this->newLine();

        foreach ($n1Issues as $index => $issue) {
            $num = $index + 1;

            $this->components->warn("Issue #{$num}: {$issue['table']} table");
            $this->components->twoColumnDetail('  Query Count', $issue['count']);
            $this->components->twoColumnDetail('  Total Time', $issue['total_time'].'ms');
            $this->components->twoColumnDetail('  Avg Time', $issue['avg_time'].'ms');
            $this->components->twoColumnDetail('  Location', $issue['location']['file'].':'.$issue['location']['line']);

            if (! $this->option('no-suggestions')) {
                $this->newLine();
                $this->line('  💡 <fg=green>Suggestion:</>');
                $this->line('     '.$issue['suggestion']);
                $this->newLine();
            }
        }
    }

    /**
     * Display duplicate queries
     */
    protected function displayDuplicates(array $duplicates): void
    {
        $this->components->warn('⚠️  Duplicate Queries Detected');
        $this->newLine();

        foreach ($duplicates as $index => $duplicate) {
            $num = $index + 1;

            $this->components->info("Duplicate #{$num}");
            $this->components->twoColumnDetail('  Occurrences', $duplicate['count']);
            $this->components->twoColumnDetail('  Total Time', $duplicate['total_time'].'ms');
            $this->line('  SQL: '.$this->truncateSql($duplicate['sql']));

            if (! $this->option('no-suggestions')) {
                $this->newLine();
                $this->line('  💡 <fg=green>Suggestion:</>');
                $this->line('     '.$duplicate['suggestion']);
                $this->newLine();
            }
        }
    }

    /**
     * Display slow queries
     */
    protected function displaySlowQueries(array $slowQueries, int $threshold): void
    {
        $this->components->warn("🐌 Slow Queries (> {$threshold}ms)");
        $this->newLine();

        $this->table(
            ['#', 'Time (ms)', 'File', 'SQL'],
            collect($slowQueries)->map(function ($query, $index) {
                return [
                    $index + 1,
                    $this->colorizeTime($query['time']),
                    ($query['file'] ?? 'unknown').':'.($query['line'] ?? '0'),
                    $this->truncateSql($query['sql']),
                ];
            })->toArray()
        );

        $this->newLine();
    }

    /**
     * Display all queries
     */
    protected function displayAllQueries(array $queries): void
    {
        $this->components->info('📋 All Queries');
        $this->newLine();

        $this->table(
            ['#', 'Time (ms)', 'Connection', 'SQL'],
            collect($queries)->map(function ($query, $index) {
                return [
                    $index + 1,
                    $this->colorizeTime($query['time']),
                    $query['connection'] ?? 'default',
                    $this->truncateSql($query['sql']),
                ];
            })->toArray()
        );
    }

    /**
     * Export report to file
     */
    protected function exportReport($logger, $n1Issues, $duplicates, $slowQueries, string $format): void
    {
        $this->info('📤 Exporting report...');

        $data = [
            'timestamp' => now()->toIso8601String(),
            'statistics' => $logger->getStatistics(),
            'n1_issues' => $n1Issues,
            'duplicates' => $duplicates,
            'slow_queries' => $slowQueries,
            'all_queries' => $logger->getQueries(),
        ];

        $path = config('query-debugger.export.path');
        $filename = str_replace(
            '{date}',
            now()->format('Y-m-d_H-i-s'),
            'query-report-{date}.'.$format
        );

        $fullPath = $path.'/'.$filename;

        // Create directory if it doesn't exist
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Export based on format
        if ($format === 'json') {
            file_put_contents($fullPath, json_encode($data, JSON_PRETTY_PRINT));
        }

        $this->components->info("Report exported to: {$fullPath}");
        $this->newLine();
    }

    /**
     * Display final summary
     */
    protected function displayFinalSummary($n1Issues, $duplicates, $slowQueries): void
    {
        $totalIssues = count($n1Issues) + count($duplicates) + count($slowQueries);

        if ($totalIssues === 0) {
            $this->components->info('✅ No issues detected! Your queries look good.');
        } else {
            $this->components->warn("Found {$totalIssues} total issue(s)");

            if (! empty($n1Issues)) {
                $this->components->bulletList([
                    count($n1Issues).' N+1 query problem(s)',
                ]);
            }

            if (! empty($duplicates)) {
                $this->components->bulletList([
                    count($duplicates).' duplicate query issue(s)',
                ]);
            }

            if (! empty($slowQueries)) {
                $this->components->bulletList([
                    count($slowQueries).' slow query issue(s)',
                ]);
            }
        }
    }

    /**
     * Truncate SQL for display
     */
    protected function truncateSql(string $sql, int $maxLength = 80): string
    {
        if (strlen($sql) <= $maxLength) {
            return $sql;
        }

        return substr($sql, 0, $maxLength).'...';
    }

    /**
     * Colorize time based on thresholds
     */
    protected function colorizeTime(float $time): string
    {
        if ($time > config('query-debugger.critical_threshold', 1000)) {
            return "<fg=red>{$time}</>";
        }

        if ($time > config('query-debugger.slow_threshold', 500)) {
            return "<fg=yellow>{$time}</>";
        }

        return "<fg=green>{$time}</>";
    }
}
