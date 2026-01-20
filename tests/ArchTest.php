<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('services are final')
    ->expect('Mash\LaravelQueryDebugger\Services')
    ->toBeFinal();

arch('commands extend base command')
    ->expect('Mash\LaravelQueryDebugger\Commands')
    ->toExtend('Illuminate\Console\Command');

arch('facades extend base facade')
    ->expect('Mash\LaravelQueryDebugger\Facades')
    ->toExtend('Illuminate\Support\Facades\Facade');
