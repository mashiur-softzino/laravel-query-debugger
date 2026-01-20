<?php

namespace Mash\LaravelQueryDebugger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mash\LaravelQueryDebugger\Services\QueryLogger
 *
 * @method static void log(array $queryData)
 * @method static array getQueries()
 * @method static array getSlowQueries(?int $threshold = null)
 * @method static array getCriticalQueries()
 * @method static int getTotalQueries()
 * @method static float getTotalTime()
 * @method static void clear()
 * @method static array getStatistics()
 */
class QueryDebugger extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'query-debugger';
    }
}
