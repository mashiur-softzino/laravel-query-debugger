# Laravel Query Debugger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mash/laravel-query-debugger.svg?style=flat-square)](https://packagist.org/packages/mash/laravel-query-debugger)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mash/laravel-query-debugger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mash/laravel-query-debugger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mash/laravel-query-debugger.svg?style=flat-square)](https://packagist.org/packages/mash/laravel-query-debugger)

**Auto-detect N+1 queries, duplicate queries, slow queries, and get actionable optimization suggestions for your Laravel Eloquent queries.**

Stop wasting hours debugging query performance! This package automatically detects N+1 query problems, identifies slow queries, duplicate queries, SELECT * issues, and provides you with exact code suggestions to fix them.

## Features

- **Visual Debug Toolbar** - Laravel Debugbar-like toolbar at the bottom of your page (works with Blade, Inertia, Vue, React)
- **Performance Score** - Get a score (0-100) with grades (A+, A, B, C, D, F) for your page's query performance
- **Automatic N+1 Detection** - Catches N+1 query patterns and tells you exactly where they occur
- **Duplicate Query Detection** - Finds exact same queries running multiple times
- **SELECT * Warnings** - Identifies queries fetching all columns unnecessarily
- **Slow Query Identification** - Flags queries exceeding your performance thresholds
- **Actionable Suggestions** - Get before/after code examples to fix issues
- **Context Tracking** - Know exactly which file and line triggered each query
- **Beautiful Reports** - Export detailed HTML or JSON reports
- **Zero Config** - Works out of the box with sensible defaults
- **Production Safe** - Automatically disabled in production
- **Framework Agnostic** - Works with Blade, Inertia.js, Vue.js, React, and any SPA

## Screenshots

### Visual Debug Toolbar (Collapsed)
```
┌─────────────────────────────────────────────────────────────────────┐
│ ⚡ Query Debugger │ Queries: 12 │ Time: 45ms │ N+1: 2 │ ▲ Expand   │
└─────────────────────────────────────────────────────────────────────┘
```

### Visual Debug Toolbar (Expanded)
```
┌─────────────────────────────────────────────────────────────────────┐
│ PERFORMANCE SCORE: 75/100 (C - Needs Improvement)                   │
├─────────────────────────────────────────────────────────────────────┤
│ Statistics          │ N+1 Issues           │ Other Issues           │
│ Total Queries: 12   │ 🚨 Issue #1          │ 🔄 Duplicate Query #1  │
│ Total Time: 45ms    │ Pattern: SELECT...   │ 3x executed            │
│ Avg Time: 3.75ms    │ Count: 5 queries     │ ⚠️ SELECT * Query #1   │
│                     │ 💡 Use eager loading │                        │
└─────────────────────────────────────────────────────────────────────┘
```

## Installation

You can install the package via composer:

```bash
composer require mash/laravel-query-debugger --dev
```

The package will automatically register itself via Laravel's auto-discovery.

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag="query-debugger-config"
```

This will create a `config/query-debugger.php` file where you can customize:

```php
return [
    'enabled' => env('QUERY_DEBUGGER_ENABLED', env('APP_DEBUG', false)),
    'slow_threshold' => 500,      // ms
    'critical_threshold' => 1000, // ms
    'n1_threshold' => 5,          // how many repeated queries = N+1
    // ... more options
];
```

### Environment Variables

Add these to your `.env` file:

```env
QUERY_DEBUGGER_ENABLED=true
QUERY_DEBUGGER_SLOW_THRESHOLD=500
QUERY_DEBUGGER_N1_THRESHOLD=5
```

## Usage

### Visual Debug Toolbar (Recommended)

The package automatically injects a debug toolbar at the bottom of your HTML pages. No configuration needed!

**Features:**
- Collapsible/expandable panel
- Drag to resize
- Performance score with grade (A+, A, B, C, D, F)
- N+1 issue details with fix suggestions
- Duplicate query detection
- SELECT * warnings
- Works with all frontend frameworks (Blade, Inertia, Vue, React)

### Middleware Setup

Add the middleware to see the toolbar. In `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \Mash\LaravelQueryDebugger\Middleware\QueryDebuggerMiddleware::class,
    ],
];
```

Or for Laravel 11+ in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \Mash\LaravelQueryDebugger\Middleware\QueryDebuggerMiddleware::class,
    ]);
})
```

### Command Line Usage

```bash
php artisan debug:queries
```

You'll get a beautiful console output showing:

```
🔍 Analyzing Database Queries...

Total Queries .................................................. 45
Total Time ................................................... 1250ms
Average Time ................................................. 27.8ms
Slow Queries ................................................. 3

❌ N+1 Query Problems Detected

Issue #1: posts table
  Query Count ............................................... 10
  Total Time ................................................ 450ms
  Avg Time .................................................. 45ms
  Location ................... app/Http/Controllers/UserController.php:25

  💡 Suggestion:
     Add eager loading: use ->with('posts') on your parent query.
```

### Command Options

```bash
# Show queries slower than 300ms
php artisan debug:queries --slow=300

# Show all queries (not just issues)
php artisan debug:queries --all

# Export report to JSON
php artisan debug:queries --export=json

# Export report to HTML
php artisan debug:queries --export=html

# Hide fix suggestions
php artisan debug:queries --no-suggestions
```

### Using the Facade

You can also interact with the query logger programmatically:

```php
use Mash\LaravelQueryDebugger\Facades\QueryDebugger;

// Get all logged queries
$queries = QueryDebugger::getQueries();

// Get only slow queries
$slowQueries = QueryDebugger::getSlowQueries(500);

// Get statistics
$stats = QueryDebugger::getStatistics();
// [
//     'total_queries' => 45,
//     'total_time' => 1250.5,
//     'avg_time' => 27.8,
//     'slow_queries' => 3,
//     ...
// ]

// Clear logged queries
QueryDebugger::clear();
```

## Performance Scoring

The package calculates a performance score (0-100) based on:

| Factor | Impact |
|--------|--------|
| Total queries > 10 | -5 points |
| Total queries > 20 | -10 points |
| Total time > 100ms | -5 points |
| Total time > 500ms | -15 points |
| Each N+1 issue | -15 points (max -30) |
| Each duplicate query | -5 points (max -15) |
| Each SELECT * query | -2 points (max -10) |
| Slow queries | -5 points each (max -15) |

### Grade Scale

| Score | Grade | Label |
|-------|-------|-------|
| 95-100 | A+ | Excellent |
| 85-94 | A | Good |
| 70-84 | B | Needs Improvement |
| 50-69 | C | Poor |
| 25-49 | D | Very Poor |
| 0-24 | F | Critical |

## Real-World Examples

### N+1 Problem Detection

**Your Code:**
```php
// ❌ This creates an N+1 problem
public function index()
{
    $users = User::all();

    foreach ($users as $user) {
        echo $user->posts->count(); // Queries posts for each user!
    }
}
```

**Package Output:**
```
❌ N+1 Detected at app/Http/Controllers/UserController.php:15
- Queries: 1 (User) + 10 (Posts per User) = 11 total
- Time: 750ms
- Suggestion: Add ->with('posts') to your User query

💡 Fix:
$users = User::with('posts')->get();
```

**Fixed Code:**
```php
// ✅ Optimized with eager loading
public function index()
{
    $users = User::with('posts')->get();

    foreach ($users as $user) {
        echo $user->posts->count(); // No additional queries!
    }
}
```

### Duplicate Query Detection

**Your Code:**
```php
// ❌ Same query runs multiple times
public function dashboard()
{
    $user = User::find(1);
    // ... later in code ...
    $user = User::find(1); // Same query again!
}
```

**Package Output:**
```
🔄 Duplicate Query Detected
SQL: SELECT * FROM users WHERE id = 1
Executions: 2x
Wasted Time: 15ms

💡 Suggestion:
Store the result in a variable and reuse it, or use caching.
```

### SELECT * Warning

**Your Code:**
```php
// ❌ Fetching all columns when you only need a few
$users = User::all();
```

**Package Output:**
```
⚠️ SELECT * Query Detected
SQL: SELECT * FROM users
Location: app/Http/Controllers/UserController.php:10

💡 Better approach:
User::select(['id', 'name', 'email'])->get()
```

## Configuration Reference

### Thresholds

```php
'slow_threshold' => 500,      // Queries > 500ms flagged as slow
'critical_threshold' => 1000, // Queries > 1000ms flagged as critical
'n1_threshold' => 5,          // Pattern repeats 5+ times = N+1
```

### Detection Settings

```php
'detection' => [
    'n1' => true,              // Detect N+1 problems
    'duplicate' => true,       // Detect duplicate queries
    'select_all' => true,      // Detect SELECT * queries
],
```

### Middleware Settings

```php
'middleware' => [
    'auto_enable' => true,
    'exclude_paths' => [
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
    ],
],
```

### Context Tracking

```php
'track_context' => true, // Track file/line for each query
```

> **Note:** Uses `debug_backtrace()` which has performance overhead. Automatically disabled in production.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mash](https://github.com/mash)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

If you find this package helpful, please consider giving it a ⭐ on GitHub!

For issues or questions, please open an issue on [GitHub](https://github.com/mash/laravel-query-debugger/issues).
