<?php

use Illuminate\Support\Facades\DB;
use Mash\LaravelQueryDebugger\Services\QueryLogger;

beforeEach(function () {
    // Create a test table
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
});

it('can log queries', function () {
    $logger = app(QueryLogger::class);
    $logger->clear();

    // Execute a query
    DB::table('users')->where('id', 1)->first();

    $queries = $logger->getQueries();

    expect($queries)->toHaveCount(1);
    expect($queries[0])->toHaveKeys(['sql', 'bindings', 'time', 'connection']);
});

it('can detect slow queries', function () {
    $logger = app(QueryLogger::class);
    $logger->clear();

    // Manually log a slow query for testing
    $logger->log([
        'sql' => 'SELECT * FROM users',
        'bindings' => [],
        'time' => 600,  // 600ms - above default 500ms threshold
        'connection' => 'testing',
    ]);

    $slowQueries = $logger->getSlowQueries();

    expect($slowQueries)->toHaveCount(1);
    expect($slowQueries[0]['time'])->toBe(600);
});

it('can calculate statistics', function () {
    $logger = app(QueryLogger::class);
    $logger->clear();

    // Log multiple queries
    $logger->log(['sql' => 'SELECT 1', 'bindings' => [], 'time' => 10, 'connection' => 'testing']);
    $logger->log(['sql' => 'SELECT 2', 'bindings' => [], 'time' => 20, 'connection' => 'testing']);
    $logger->log(['sql' => 'SELECT 3', 'bindings' => [], 'time' => 30, 'connection' => 'testing']);

    $stats = $logger->getStatistics();

    expect($stats['total_queries'])->toBe(3);
    expect($stats['total_time'])->toBe(60.0);
    expect($stats['avg_time'])->toBe(20.0);
});

it('can clear logged queries', function () {
    $logger = app(QueryLogger::class);
    $logger->clear(); // Clear any queries from beforeEach

    // Log a query
    $logger->log(['sql' => 'SELECT 1', 'bindings' => [], 'time' => 10, 'connection' => 'testing']);

    expect($logger->getQueries())->toHaveCount(1);

    // Clear
    $logger->clear();

    expect($logger->getQueries())->toBeEmpty();
});
