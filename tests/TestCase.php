<?php

namespace Mash\LaravelQueryDebugger\Tests;

use Mash\LaravelQueryDebugger\QueryDebuggerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup if needed
    }

    protected function getPackageProviders($app)
    {
        return [
            QueryDebuggerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Configure package for testing
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Enable query debugger for tests
        config()->set('query-debugger.enabled', true);
    }
}
