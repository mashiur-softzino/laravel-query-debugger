<?php

namespace Mash\LaravelQueryDebugger;

use Illuminate\Support\Facades\DB;
use Mash\LaravelQueryDebugger\Commands\AnalyzeQueriesCommand;
use Mash\LaravelQueryDebugger\Services\QueryLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QueryDebuggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-query-debugger')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(AnalyzeQueriesCommand::class);
    }

    public function packageRegistered(): void
    {
        // Register the QueryLogger as a singleton so it persists throughout the request
        $this->app->singleton(QueryLogger::class, function () {
            return new QueryLogger();
        });

        // Register the facade accessor
        $this->app->bind('query-debugger', function () {
            return $this->app->make(QueryLogger::class);
        });
    }

    public function packageBooted(): void
    {
        // Only enable query logging if:
        // 1. Package is enabled in config
        // 2. We're in a local/development environment (safety check)
        if ($this->shouldEnableQueryLogging()) {
            $this->enableQueryLogging();
        }
    }

    /**
     * Determine if query logging should be enabled
     */
    protected function shouldEnableQueryLogging(): bool
    {
        // Check if explicitly enabled in config
        if (!config('query-debugger.enabled', false)) {
            return false;
        }

        // Safety check: don't run in production unless explicitly allowed
        if ($this->app->environment('production') && !config('query-debugger.allow_in_production', false)) {
            return false;
        }

        return true;
    }

    /**
     * Enable query logging by attaching DB::listen event
     */
    protected function enableQueryLogging(): void
    {
        $logger = $this->app->make(QueryLogger::class);

        // Listen to all database queries
        DB::listen(function ($query) use ($logger) {
            // Capture backtrace here to get accurate caller information
            $context = $this->captureQueryContext();

            // Log the query with all its details
            $logger->log([
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'connection' => $query->connectionName,
                'context' => $context, // Pass the context we captured
            ]);
        });
    }

    /**
     * Capture the context where the query was called from
     * Returns file, line, class, and method information
     */
    protected function captureQueryContext(): array
    {
        // Get a deeper backtrace to ensure we capture the actual application code
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 50);

        // Skip frames to find the actual application code
        foreach ($backtrace as $trace) {
            if (!isset($trace['file'])) {
                continue;
            }

            $file = $trace['file'];
            $class = $trace['class'] ?? null;

            // Skip vendor files (framework, packages)
            if (str_contains($file, '/vendor/')) {
                continue;
            }

            // Skip this package's own files - check both file path and class namespace
            if (
                str_contains($file, 'laravel-query-debugger') ||
                str_contains($file, 'LaravelQueryDebugger') ||
                str_contains($file, 'QueryDebuggerServiceProvider') ||
                ($class && str_contains($class, 'Mash\\LaravelQueryDebugger'))
            ) {
                continue;
            }

            // Skip Laravel database and connection files
            if (
                str_contains($file, '/database/') ||
                str_contains($file, '/Database/')
            ) {
                continue;
            }

            // Found the application code - extract class and method
            $method = $trace['function'] ?? null;

            // If we have a class, format it nicely
            $location = '';
            if ($class && $method) {
                $location = class_basename($class) . '::' . $method . '()';
            } elseif ($method) {
                $location = $method . '()';
            }

            return [
                'file' => $this->getRelativePathFromBase($file),
                'line' => $trace['line'] ?? 0,
                'class' => $class,
                'method' => $method,
                'location' => $location,
            ];
        }

        // Fallback if we couldn't find application code
        return [
            'file' => 'unknown',
            'line' => 0,
            'class' => null,
            'method' => null,
            'location' => 'unknown',
        ];
    }

    /**
     * Convert absolute path to relative path from base_path()
     */
    protected function getRelativePathFromBase(string $absolutePath): string
    {
        $basePath = base_path();

        if (str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath) + 1);
        }

        return $absolutePath;
    }
}
