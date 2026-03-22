<?php

use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'plan.feature' => EnsurePlanFeature::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Process recurring invoices every day at 06:00
        $schedule->command('invoices:process-recurring')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Expire overdue subscriptions every day at 00:05
        $schedule->command('subscriptions:expire')
            ->dailyAt('00:05')
            ->withoutOverlapping();

        // Log expired trials every day at 00:10
        $schedule->command('trials:expire')
            ->dailyAt('00:10')
            ->withoutOverlapping();

        // Log overdue invoice reminders every weekday morning at 08:00
        $schedule->command('invoices:overdue-reminders')
            ->weekdays()
            ->at('08:00')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
