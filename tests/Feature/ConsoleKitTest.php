<?php

declare(strict_types=1);

use Cbox\Console\Kit\ConsoleManager;
use Cbox\Console\Kit\Facades\Console;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('lets a plugin add a nav area, and orders areas', function (): void {
    Console::nav()->area('overview', 'Overview', 'dashboard', 10)->page('dashboard', 'Overview');
    Console::nav()->area('billing', 'Billing', 'card', 90)->page('billing.plan', 'Plan', feature: 'billing');

    $areas = Console::nav()->areas();

    expect($areas[0]->key)->toBe('overview')
        ->and($areas[1]->key)->toBe('billing')
        ->and($areas[1]->pages()[0]->route)->toBe('billing.plan')
        ->and($areas[1]->pages()[0]->feature)->toBe('billing');
});

it('adds pages to an existing host area without editing the host', function (): void {
    Console::nav()->area('overview', 'Overview')->page('dashboard', 'Overview');
    Console::nav()->area('overview')->page('usage', 'Usage'); // a plugin extends the host area

    $routes = array_map(fn ($p) => $p->route, Console::nav()->areas()[0]->pages());

    expect($routes)->toContain('dashboard', 'usage');
});

it('gates features deny-by-default and via a live resolver', function (): void {
    expect(Console::featureActive('billing'))->toBeFalse(); // never registered

    Console::features()->register('billing', false);
    expect(Console::featureActive('billing'))->toBeFalse();

    // A live resolver — evaluated on each check (by-reference so the test can flip it).
    $on = true;
    Console::features()->register('billing', function () use (&$on): bool {
        return $on;
    });
    expect(Console::featureActive('billing'))->toBeTrue();

    $on = false;
    expect(Console::featureActive('billing'))->toBeFalse();

    Console::features()->register('sso', true);
    expect(Console::features()->activeKeys())->toContain('sso')->not->toContain('billing');
});

it('renders slot contributions in order', function (): void {
    Console::slots()->add('cards', fn (): string => '<b>second</b>', 20);
    Console::slots()->add('cards', fn (): string => '<i>first</i>', 10);

    expect(Console::slots()->has('cards'))->toBeTrue()
        ->and(Console::slots()->render('cards'))->toBe('<i>first</i><b>second</b>')
        ->and(Console::slots()->render('empty'))->toBe('');
});

it('contributes a dashboard card via the well-known slot', function (): void {
    Console::dashboardCard(fn (): string => '<div>plan</div>');

    expect(Console::slots()->render(ConsoleManager::DASHBOARD_CARDS))->toBe('<div>plan</div>');
});

it('renders a slot through the @consoleSlot directive', function (): void {
    Console::slots()->add('x', fn (): string => 'HELLO');

    expect(trim(Blade::render("@consoleSlot('x')")))->toBe('HELLO');
});

it('guards a route with the console.feature middleware', function (): void {
    Console::features()->register('billing', false);
    Route::middleware('console.feature:billing')->get('/billing-x', fn (): string => 'ok');
    $this->get('/billing-x')->assertNotFound();

    Console::features()->register('reports', true);
    Route::middleware('console.feature:reports')->get('/reports-x', fn (): string => 'ok');
    $this->get('/reports-x')->assertOk()->assertSee('ok');
});
