<?php

namespace Mash\LaravelQueryDebugger\Services;

final class ReportGenerator
{
    /**
     * Generate a complete report with all analysis data
     */
    public function generate(
        QueryLogger $logger,
        N1Detector $detector,
        SuggestionEngine $suggestionEngine
    ): array {
        $queries = $logger->getQueries();
        $n1Issues = $detector->detect($queries);
        $duplicates = $detector->detectDuplicates($queries);
        $slowQueries = $logger->getSlowQueries();

        return [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'app_name' => config('app.name'),
                'environment' => app()->environment(),
            ],
            'statistics' => $logger->getStatistics(),
            'issues' => [
                'n1' => $n1Issues,
                'duplicates' => $duplicates,
                'slow_queries' => array_values($slowQueries),
            ],
            'suggestions' => [
                'n1' => $suggestionEngine->generateN1Suggestions($n1Issues),
                'duplicates' => $suggestionEngine->generateDuplicateSuggestions($duplicates),
                'slow_queries' => $suggestionEngine->generateSlowQuerySuggestions($slowQueries),
            ],
            'queries' => $queries,
        ];
    }

    /**
     * Export report to JSON format
     */
    public function exportJson(array $report): string
    {
        $path = $this->getExportPath('json');

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Export report to HTML format
     */
    public function exportHtml(array $report): string
    {
        $path = $this->getExportPath('html');

        $html = $this->generateHtmlReport($report);

        file_put_contents($path, $html);

        return $path;
    }

    /**
     * Get the export file path
     */
    protected function getExportPath(string $format): string
    {
        $dir = config('query-debugger.export.path');
        $filenameFormat = config('query-debugger.export.filename_format', 'query-report-{date}.{format}');

        // Create directory if it doesn't exist
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = str_replace(
            ['{date}', '{format}'],
            [now()->format('Y-m-d_H-i-s'), $format],
            $filenameFormat
        );

        return $dir.'/'.$filename;
    }

    /**
     * Generate HTML report
     */
    protected function generateHtmlReport(array $report): string
    {
        $stats = $report['statistics'];
        $n1Issues = $report['issues']['n1'];
        $duplicates = $report['issues']['duplicates'];
        $slowQueries = $report['issues']['slow_queries'];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query Debugger Report - {$report['metadata']['generated_at']}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .issue {
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #ff6b6b;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .issue.warning { border-left-color: #feca57; }
        .issue.info { border-left-color: #48dbfb; }
        .issue-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        .issue-detail {
            font-size: 14px;
            color: #666;
            margin: 5px 0;
        }
        .suggestion {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
            color: #2e7d32;
        }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 10px 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px;
        }
        .badge.critical { background: #ff6b6b; color: white; }
        .badge.high { background: #feca57; color: #333; }
        .badge.medium { background: #48dbfb; color: white; }
        .badge.low { background: #95afc0; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Query Debugger Report</h1>
            <p>Generated at: {$report['metadata']['generated_at']}</p>
            <p>Application: {$report['metadata']['app_name']} ({$report['metadata']['environment']})</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Queries</div>
                <div class="stat-value">{$stats['total_queries']}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Time</div>
                <div class="stat-value">{$stats['total_time']}<small>ms</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Time</div>
                <div class="stat-value">{$stats['avg_time']}<small>ms</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Slow Queries</div>
                <div class="stat-value">{$stats['slow_queries']}</div>
            </div>
        </div>
HTML;

        // N+1 Issues
        if (! empty($n1Issues)) {
            $html .= '<div class="section">';
            $html .= '<h2>❌ N+1 Query Problems</h2>';

            foreach ($n1Issues as $issue) {
                $severity = $this->calculateSeverity($issue);
                $html .= <<<ISSUE
                <div class="issue">
                    <div class="issue-title">
                        <span class="badge {$severity}">{$severity}</span>
                        N+1 on '{$issue['table']}' table
                    </div>
                    <div class="issue-detail">📍 Location: {$issue['location']['file']}:{$issue['location']['line']}</div>
                    <div class="issue-detail">🔢 Query Count: {$issue['count']}</div>
                    <div class="issue-detail">⏱️ Total Time: {$issue['total_time']}ms (avg: {$issue['avg_time']}ms)</div>
                    <div class="suggestion">💡 {$issue['suggestion']}</div>
                </div>
ISSUE;
            }

            $html .= '</div>';
        }

        // Duplicate Queries
        if (! empty($duplicates)) {
            $html .= '<div class="section">';
            $html .= '<h2>⚠️ Duplicate Queries</h2>';

            foreach ($duplicates as $dup) {
                $html .= <<<DUP
                <div class="issue warning">
                    <div class="issue-title">Exact query runs {$dup['count']} times</div>
                    <div class="code">{$dup['sql']}</div>
                    <div class="issue-detail">⏱️ Total Time Wasted: {$dup['total_time']}ms</div>
                    <div class="suggestion">💡 {$dup['suggestion']}</div>
                </div>
DUP;
            }

            $html .= '</div>';
        }

        // Slow Queries
        if (! empty($slowQueries)) {
            $html .= '<div class="section">';
            $html .= '<h2>🐌 Slow Queries</h2>';

            foreach ($slowQueries as $query) {
                $time = $query['time'];
                $severity = $time > 1000 ? 'critical' : 'high';

                $html .= <<<SLOW
                <div class="issue">
                    <div class="issue-title">
                        <span class="badge {$severity}">{$time}ms</span>
                        Slow Query
                    </div>
                    <div class="issue-detail">📍 {$query['file']}:{$query['line']}</div>
                    <div class="code">{$query['sql']}</div>
                </div>
SLOW;
            }

            $html .= '</div>';
        }

        // Final summary
        $totalIssues = count($n1Issues) + count($duplicates) + count($slowQueries);

        $html .= <<<FOOTER
        <div class="section">
            <h2>📊 Summary</h2>
            <p style="font-size: 18px;">
                Found <strong>{$totalIssues} total issue(s)</strong>
            </p>
        </div>
    </div>
</body>
</html>
FOOTER;

        return $html;
    }

    /**
     * Calculate severity for badges
     */
    protected function calculateSeverity(array $issue): string
    {
        $count = $issue['count'] ?? 0;
        $time = $issue['total_time'] ?? 0;

        if ($count >= 20 || $time > 2000) {
            return 'critical';
        }
        if ($count >= 10 || $time > 1000) {
            return 'high';
        }
        if ($count >= 5 || $time > 500) {
            return 'medium';
        }

        return 'low';
    }
}
