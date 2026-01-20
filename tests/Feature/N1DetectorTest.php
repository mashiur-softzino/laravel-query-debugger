<?php

use Mash\LaravelQueryDebugger\Services\N1Detector;

it('can detect N+1 query problems', function () {
    $detector = app(N1Detector::class);

    // Simulate N+1 pattern: 1 parent query + 10 child queries
    $queries = [
        // Parent query
        ['sql' => 'SELECT * FROM users', 'bindings' => [], 'time' => 5, 'file' => 'Controller.php', 'line' => 10],
    ];

    // Child queries (same pattern, different values) - simulating relationship access
    for ($i = 1; $i <= 10; $i++) {
        $queries[] = [
            'sql' => "SELECT * FROM posts WHERE user_id = {$i}",
            'bindings' => [$i],
            'time' => 10,
            'file' => 'Controller.php',
            'line' => 15,
            'class' => 'Illuminate\Database\Eloquent\Relations\HasMany',
            'method' => 'getRelationValue',
        ];
    }

    $issues = $detector->detect($queries);

    expect($issues)->toHaveCount(1);
    expect($issues[0]['type'])->toBe('n+1');
    expect($issues[0]['count'])->toBe(10);
    expect($issues[0]['table'])->toBe('posts');
});

it('normalizes queries correctly', function () {
    $detector = new N1Detector();

    $sql1 = 'SELECT * FROM users WHERE id = 1';
    $sql2 = 'SELECT * FROM users WHERE id = 2';
    $sql3 = 'SELECT * FROM users WHERE id = 999';

    // Use Reflection to access protected method
    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('normalizeQuery');
    $method->setAccessible(true);

    $normalized1 = $method->invoke($detector, $sql1);
    $normalized2 = $method->invoke($detector, $sql2);
    $normalized3 = $method->invoke($detector, $sql3);

    // All should normalize to the same pattern
    expect($normalized1)->toBe($normalized2);
    expect($normalized2)->toBe($normalized3);
    expect($normalized1)->toBe('select * from users where id = ?');
});

it('can detect duplicate queries', function () {
    $detector = app(N1Detector::class);

    // Same exact query multiple times
    $queries = [
        ['sql' => 'SELECT * FROM users WHERE active = 1', 'bindings' => [1], 'time' => 10, 'file' => 'A.php', 'line' => 5],
        ['sql' => 'SELECT * FROM users WHERE active = 1', 'bindings' => [1], 'time' => 10, 'file' => 'B.php', 'line' => 10],
        ['sql' => 'SELECT * FROM users WHERE active = 1', 'bindings' => [1], 'time' => 10, 'file' => 'C.php', 'line' => 15],
    ];

    $duplicates = $detector->detectDuplicates($queries);

    expect($duplicates)->toHaveCount(1);
    expect($duplicates[0]['type'])->toBe('duplicate');
    expect($duplicates[0]['count'])->toBe(3);
});

it('does not flag queries below N+1 threshold', function () {
    $detector = app(N1Detector::class);

    // Only 3 similar queries (below default threshold of 5)
    $queries = [
        ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'bindings' => [1], 'time' => 10],
        ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'bindings' => [2], 'time' => 10],
        ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'bindings' => [3], 'time' => 10],
    ];

    $issues = $detector->detect($queries);

    expect($issues)->toBeEmpty();
});
