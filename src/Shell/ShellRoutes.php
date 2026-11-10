<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Http\Controllers\ShellContextController;
use Cbox\Console\Kit\Http\Controllers\ShellHomeController;
use Cbox\Console\Kit\Http\Controllers\ShellNotificationsController;
use Cbox\Console\Kit\Http\Controllers\ShellPreferencesController;
use Cbox\Console\Kit\Http\Controllers\ShellSearchController;
use Cbox\Console\Kit\Http\Controllers\ShellSwitchController;
use Illuminate\Support\Facades\Route;

/**
 * The shell's same-origin endpoints, under `/shell` by default:
 *
 *  - GET|POST switch                          console.shell.switch
 *  - GET      context/{level}                 console.shell.context.list
 *  - POST     context/{level}                 console.shell.context.create
 *  - GET      context/{level}/{id}/settings   console.shell.context.settings
 *  - GET      search                          console.shell.search
 *  - GET      notifications                   console.shell.notifications
 *  - POST     notifications/read              console.shell.notifications.read
 *  - PATCH    preferences                     console.shell.preferences
 *
 * They need a signed-in person: `web` and `auth` by default.
 *
 * {@see home()} adds `GET /` (console.shell.home), which lands on the last context.
 */
final class ShellRoutes
{
    /**
     * @param  list<string>  $middleware
     */
    public static function register(string $prefix = 'shell', array $middleware = ['web', 'auth']): void
    {
        Route::middleware($middleware)->prefix($prefix)->group(static function (): void {
            Route::match(['GET', 'POST'], 'switch', ShellSwitchController::class)->name(ShellBuilder::ROUTE_SWITCH);
            Route::get('context/{level}', [ShellContextController::class, 'index'])->name(ShellBuilder::ROUTE_LIST);
            Route::post('context/{level}', [ShellContextController::class, 'store'])
                ->middleware('throttle:30,1')->name(ShellBuilder::ROUTE_CREATE);
            Route::get('context/{level}/{id}/settings', [ShellContextController::class, 'settings'])->name(ShellBuilder::ROUTE_SETTINGS);
            Route::get('search', ShellSearchController::class)->name(ShellBuilder::ROUTE_SEARCH);
            Route::get('notifications', [ShellNotificationsController::class, 'index'])->name(ShellBuilder::ROUTE_NOTIFICATIONS);
            Route::post('notifications/read', [ShellNotificationsController::class, 'read'])->name(ShellBuilder::ROUTE_NOTIFICATIONS.'.read');
            Route::patch('preferences', ShellPreferencesController::class)->name(ShellBuilder::ROUTE_PREFERENCES);
        });
    }

    /**
     * `GET {$uri}` → the person's last context, else their first top-level item, else the
     * top level's collection (spec §5.6: "`/` lands there").
     *
     * @param  list<string>  $middleware
     */
    public static function home(string $uri = '/', array $middleware = ['web', 'auth']): void
    {
        Route::middleware($middleware)->get($uri, ShellHomeController::class)->name(ShellBuilder::ROUTE_HOME);
    }
}
