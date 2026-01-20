<?php

// Configuration for Mash/LaravelQueryDebugger

return [

    /*
    |--------------------------------------------------------------------------
    | Query Debugger Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the query debugger. When disabled, no queries will be
    | logged and no performance analysis will be performed. It's recommended
    | to disable this in production for performance reasons.
    |
    | You can control this via QUERY_DEBUGGER_ENABLED in your .env file.
    |
    */

    'enabled' => env('QUERY_DEBUGGER_ENABLED', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Slow Query Threshold
    |--------------------------------------------------------------------------
    |
    | Define what constitutes a "slow" query in milliseconds. Any query that
    | takes longer than this threshold will be flagged as slow and included
    | in the slow queries report.
    |
    | Default: 500ms
    |
    */

    'slow_threshold' => env('QUERY_DEBUGGER_SLOW_THRESHOLD', 500),

    /*
    |--------------------------------------------------------------------------
    | Critical Query Threshold
    |--------------------------------------------------------------------------
    |
    | Define what constitutes a "critical" slow query in milliseconds. Queries
    | exceeding this threshold are considered critical performance issues.
    |
    | Default: 1000ms (1 second)
    |
    */

    'critical_threshold' => env('QUERY_DEBUGGER_CRITICAL_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configure which types of query issues should be detected:
    | - n1: Detect N+1 query problems
    | - duplicate: Detect duplicate/repeated queries
    | - missing_indexes: Suggest missing database indexes (future feature)
    |
    */

    'detection' => [
        'n1' => env('QUERY_DEBUGGER_DETECT_N1', true),
        'duplicate' => env('QUERY_DEBUGGER_DETECT_DUPLICATE', true),
        'missing_indexes' => false, // Future feature
    ],

    /*
    |--------------------------------------------------------------------------
    | N+1 Detection Sensitivity
    |--------------------------------------------------------------------------
    |
    | How many times should a query pattern repeat before it's flagged as N+1?
    | Lower values = more sensitive (may have false positives)
    | Higher values = less sensitive (may miss some N+1 issues)
    |
    | Default: 5 (if same query runs 5+ times, flag as N+1)
    |
    */

    'n1_threshold' => env('QUERY_DEBUGGER_N1_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Configure how and where query reports should be exported.
    | Supported formats: json, html, csv
    |
    */

    'export' => [
        'formats' => ['json', 'html'],
        'path' => storage_path('logs/query-debugger'),
        'filename_format' => 'query-report-{date}.{format}', // {date} will be replaced
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Settings
    |--------------------------------------------------------------------------
    |
    | Configure middleware behavior for automatic query logging.
    |
    */

    'middleware' => [
        // Automatically enable query logging for web requests
        'auto_enable' => env('QUERY_DEBUGGER_MIDDLEWARE_AUTO', true),

        // Exclude specific paths from query logging
        'exclude_paths' => [
            'telescope/*',
            'horizon/*',
            'debugbar/*',
            '_debugbar/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Tracking
    |--------------------------------------------------------------------------
    |
    | Track which file and line number triggered each query. This helps you
    | quickly locate the source of slow or N+1 queries.
    |
    | Warning: This uses debug_backtrace() which has performance overhead.
    |
    */

    'track_context' => env('QUERY_DEBUGGER_TRACK_CONTEXT', true),

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    |
    | Configure how query information is displayed in console output.
    |
    */

    'display' => [
        // Show full SQL queries or truncate them
        'truncate_sql' => env('QUERY_DEBUGGER_TRUNCATE_SQL', true),
        'truncate_length' => 100,

        // Show query bindings
        'show_bindings' => true,

        // Colorize output (for terminals that support it)
        'colorize' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Logging Limits
    |--------------------------------------------------------------------------
    |
    | To prevent memory issues, limit the number of queries that can be logged
    | in a single request. Set to null for unlimited (not recommended).
    |
    */

    'max_queries' => env('QUERY_DEBUGGER_MAX_QUERIES', 1000),

];
