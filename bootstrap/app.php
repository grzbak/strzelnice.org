<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('shooting-range:cleanup-expired')
            //->dailyAt('14:09');
            ->everyMinute();
    })
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'approved' => \App\Http\Middleware\ApprovedUser::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'range.step' => \App\Http\Middleware\EnsureRangeStep::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
