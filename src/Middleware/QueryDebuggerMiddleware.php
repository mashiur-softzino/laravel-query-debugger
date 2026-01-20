<?php

namespace Mash\LaravelQueryDebugger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mash\LaravelQueryDebugger\Services\N1Detector;
use Mash\LaravelQueryDebugger\Services\QueryLogger;
use Mash\LaravelQueryDebugger\Services\QueryIssueDetector;
use Mash\LaravelQueryDebugger\Services\SuggestionEngine;
use Symfony\Component\HttpFoundation\Response;

class QueryDebuggerMiddleware
{
    public function __construct(
        protected QueryLogger $logger,
        protected N1Detector $detector,
        protected QueryIssueDetector $issueDetector,
        protected SuggestionEngine $suggestionEngine
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if query debugger is enabled
        if (! config('query-debugger.enabled', false)) {
            return $next($request);
        }

        // Check if middleware auto-enable is on
        if (! config('query-debugger.middleware.auto_enable', true)) {
            return $next($request);
        }

        // Check if this path should be excluded
        if ($this->shouldExclude($request)) {
            return $next($request);
        }

        // Clear previous queries at the start of the request
        $this->logger->clear();

        // Process the request (queries will be logged automatically via DB::listen)
        $response = $next($request);

        // After request completes, analyze queries and show warnings
        if (config('app.debug')) {
            $this->analyzeAndWarn($response);
        }

        return $response;
    }

    /**
     * Analyze queries and add warnings
     */
    protected function analyzeAndWarn(Response $response): void
    {
        $queries = $this->logger->getQueries();
        $stats = $this->logger->getStatistics();

        // Add debug headers
        $response->headers->set('X-Query-Count', $stats['total_queries']);
        $response->headers->set('X-Query-Time', $stats['total_time'].'ms');
        $response->headers->set('X-Query-Slow', $stats['slow_queries']);

        // Detect N+1 issues
        $n1Issues = $this->detector->detect($queries);

        // Detect other query issues (duplicates, SELECT *, etc.)
        $otherIssues = $this->issueDetector->detectAllIssues($queries);

        if (! empty($n1Issues)) {
            // Add N+1 warning header
            $response->headers->set('X-Query-N1-Issues', count($n1Issues));

            // Log warnings with suggestions
            $suggestions = $this->suggestionEngine->generateN1Suggestions($n1Issues);

            foreach ($n1Issues as $index => $issue) {
                $suggestion = $suggestions[$index] ?? null;

                $logData = [
                    'pattern' => $issue['pattern'],
                    'count' => $issue['count'],
                    'table' => $issue['table'] ?? 'unknown',
                    'example' => $issue['queries'][0]['sql'] ?? 'N/A',
                ];

                // Add suggestion if available
                if ($suggestion) {
                    $logData['fix'] = $suggestion['suggestion'] ?? 'Use eager loading';
                    $logData['before_code'] = $suggestion['code_example']['before'] ?? 'Model::all()';
                    $logData['after_code'] = $suggestion['code_example']['after'] ?? "Model::with('relationship')->get()";
                    $logData['impact'] = $suggestion['impact']['percent_reduction'] ?? 'significant reduction';
                    $logData['queries_reduced'] = $suggestion['impact']['query_reduction'] ?? 'N/A';
                }

                Log::warning('N+1 Query Detected', $logData);
            }
        }

        // Log duplicate queries
        if (! empty($otherIssues['duplicates'])) {
            $response->headers->set('X-Query-Duplicates', count($otherIssues['duplicates']));

            foreach ($otherIssues['duplicates'] as $duplicate) {
                Log::warning('Duplicate Query Detected', [
                    'sql' => $duplicate['sql'],
                    'count' => $duplicate['count'],
                    'total_time' => $duplicate['total_time'].'ms',
                    'wasted_time' => $duplicate['wasted_time'].'ms',
                    'severity' => $duplicate['severity'],
                    'suggestion' => 'Cache this query result or refactor to reduce executions',
                ]);
            }
        }

        // Always inject toolbar for HTML responses (like Laravel Debugbar)
        if ($this->isHtmlResponse($response)) {
            $this->injectDebugToolbar($response, $n1Issues, $otherIssues, $stats);
        }
    }

    /**
     * Check if the current request path should be excluded from logging
     */
    protected function shouldExclude(Request $request): bool
    {
        $excludePaths = config('query-debugger.middleware.exclude_paths', []);

        foreach ($excludePaths as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if response is HTML
     */
    protected function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }

    /**
     * Inject debugger toolbar (Universal - works with Blade, Inertia, SPA)
     * Always shows toolbar, even when there are no issues (like Laravel Debugbar)
     */
    protected function injectDebugToolbar(Response $response, array $n1Issues, array $otherIssues, array $stats): void
    {
        $content = $response->getContent();

        if (! $content || ! str_contains($content, '</head>')) {
            return;
        }

        // Generate suggestions only if there are N+1 issues
        $suggestions = ! empty($n1Issues)
            ? $this->suggestionEngine->generateN1Suggestions($n1Issues)
            : [];

        // Prepare data for JavaScript
        $debugData = [
            'stats' => $stats,
            'n1Issues' => $n1Issues,
            'suggestions' => $suggestions,
            'otherIssues' => $otherIssues,
        ];

        // Inject meta tags and inline script (no external file needed)
        $jsContent = file_get_contents(__DIR__.'/../../resources/js/query-debugger.js');

        $injection = sprintf(
            '<meta name="query-debugger-enabled" content="true">'."\n".
            '<meta name="query-debugger-data" content=\'%s\'>'."\n".
            '<script>%s</script>',
            htmlspecialchars(json_encode($debugData), ENT_QUOTES, 'UTF-8'),
            $jsContent
        );

        $content = str_replace('</head>', $injection."\n</head>", $content);
        $response->setContent($content);
    }
}
