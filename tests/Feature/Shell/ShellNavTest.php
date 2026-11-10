<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Nav\NavPage;
use Cbox\Console\Kit\Shell\PaletteAction;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

beforeEach(function (): void {
    shellApp();
    $this->shellLevels(...shellLevels())->app('billing', 'Cbox Billing', '/orgs');

    // Billing names its lists without `.index`: `billing.coupons` is the list,
    // `billing.coupons.show` a record.
    Route::middleware(['web', 'auth', 'console.shell'])->group(static function (): void {
        $page = static fn (string $component): Closure => static fn () => Inertia::render($component);
        Route::get('/orgs/{org}/coupons', $page('Coupons'))->name('billing.coupons');
        Route::get('/orgs/{org}/coupons/{coupon}', $page('Coupon'))->name('billing.coupons.show');
        Route::get('/orgs/{org}/coupons/{coupon}/redemptions/{redemption}', $page('Redemption'))->name('billing.coupons.redemptions.show');
        Route::get('/orgs/{org}/drafts', $page('Drafts'))->name('billing.drafts');
        Route::get('/orgs/{org}/members', $page('Members'))->name('members');
        Route::get('/orgs/{org}/upgrade', $page('Upgrade'))->name('billing.upgrade');
        Route::get('/orgs/{org}/settings/project', $page('ProjectSettings'))->name('settings.project');
        Route::get('/orgs/{org}/settings/environment', $page('EnvironmentSettings'))->name('settings.environment');
    });
    app('router')->getRoutes()->refreshNameLookups();
});

/** The `catalog` area's pages on `$url`. */
function catalogPages(string $url): array
{
    $areas = test()->get($url, inertiaVisit())->assertOk()->json('props.shell.nav.areas');

    foreach ($areas as $area) {
        if ($area['key'] === 'catalog') {
            return $area['pages'];
        }
    }

    return [];
}

it('shows a page whose feature is off as locked, linking to the upgrade prompt', function (): void {
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts')
        ->page('billing.coupons', 'Coupons', feature: 'coupons', lockedWhenOff: true, upgradeUrl: 'billing.upgrade')
        ->page('members', 'Members', feature: 'coupons', lockedWhenOff: true)
        ->page('settings.project', 'Hidden', feature: 'coupons');

    $pages = catalogPages('/orgs/cbox/drafts');

    expect($pages)->toHaveCount(3)
        ->and($pages[1])->toBe(['key' => 'billing.coupons', 'label' => 'Coupons', 'href' => '/orgs/cbox/upgrade', 'url' => '/orgs/cbox/upgrade', 'locked' => true])
        // Without an upgrade URL a locked page links to itself, for the page to show the prompt.
        ->and($pages[2])->toBe(['key' => 'members', 'label' => 'Members', 'href' => '/orgs/cbox/members', 'url' => '/orgs/cbox/members', 'locked' => true]);

    Console::features()->register('coupons', true);

    $pages = catalogPages('/orgs/cbox/drafts');
    expect(array_column($pages, 'label'))->toBe(['Drafts', 'Coupons', 'Members', 'Hidden'])
        ->and($pages[1])->not->toHaveKey('locked')
        ->and($pages[1]['href'])->toBe('/orgs/cbox/coupons');
});

it('never shows a locked page the person may not open', function (): void {
    Gate::define('manage-coupons', fn (): bool => false);
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts')
        ->page('billing.coupons', 'Coupons', feature: 'coupons', can: 'manage-coupons', lockedWhenOff: true);

    expect(array_column(catalogPages('/orgs/cbox/drafts'), 'label'))->toBe(['Drafts']);
});

it('takes an upgrade path as well as a route name, and ignores one that is not same-origin', function (): void {
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts', feature: 'drafts', lockedWhenOff: true, upgradeUrl: '/plans?from=drafts')
        ->page('billing.coupons', 'Coupons', feature: 'coupons', lockedWhenOff: true, upgradeUrl: 'javascript:alert(1)');

    $pages = catalogPages('/orgs/cbox/members');

    expect($pages[0]['href'])->toBe('/plans?from=drafts')
        ->and($pages[1]['href'])->toBe('/orgs/cbox/coupons');
});

it('adds a count badge, only when there is something to count', function (): void {
    $seen = null;
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts', count: function (Request $request, array $context) use (&$seen): int {
            $seen = $context[0]->id;

            return 3;
        })
        ->page('billing.coupons', 'Coupons', count: 0)
        ->page('members', 'Members', count: fn (): ?int => null)
        ->page('settings.project', 'Locked', feature: 'off', lockedWhenOff: true, count: fn () => throw new RuntimeException('a locked page is never counted'));

    $pages = catalogPages('/orgs/cbox/drafts');

    expect($pages[0]['count'])->toBe(3)
        ->and($seen)->toBe('org_1')
        ->and($pages[1])->not->toHaveKey('count')
        ->and($pages[2])->not->toHaveKey('count')
        ->and($pages[3])->not->toHaveKey('count');
});

it('sends group headings and keeps the pages of a group together', function (): void {
    Console::nav()->area('catalog', 'Settings', 'settings', 30)
        ->page('members', 'Members', group: 'Organization', order: 10)
        ->page('settings.project', 'Project', group: 'Project', order: 20)
        ->page('billing.upgrade', 'Plan', group: 'Organization', order: 30)
        ->page('settings.environment', 'Environment', group: 'Environment', order: 40);

    $pages = catalogPages('/orgs/cbox/members');

    expect(array_map(fn (array $page): string => $page['group'].' / '.$page['label'], $pages))->toBe([
        'Organization / Members',
        'Organization / Plan',
        'Project / Project',
        'Environment / Environment',
    ]);
});

it('lights the list page and its area on a record page whose list has no .index', function (): void {
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts')
        ->page('billing.coupons', 'Coupons');

    $this->assertShell($this->get('/orgs/cbox/coupons/cpn_1', inertiaVisit()))
        ->assertActiveArea('catalog', 'billing.coupons');

    // The nearest parent that is a nav page, two levels down.
    $this->assertShell($this->get('/orgs/cbox/coupons/cpn_1/redemptions/red_1', inertiaVisit()))
        ->assertActiveArea('catalog', 'billing.coupons');
});

it('lets the app\'s list fallback choose the page to light', function (): void {
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts')
        ->page('billing.coupons', 'Coupons');
    Console::shell()->listFallback(fn (LaravelRoute $route): ?string => $route->getName() === 'billing.coupons.show' ? 'billing.drafts' : null);

    $this->assertShell($this->get('/orgs/cbox/coupons/cpn_1', inertiaVisit()))
        ->assertActiveArea('catalog', 'billing.drafts');
});

it('never lights a page for the app\'s name prefix alone', function (): void {
    Route::middleware(['web', 'auth', 'console.shell'])->get('/orgs/{org}/other/{id}', fn () => Inertia::render('Other'))->name('billing.other.show');
    Route::middleware(['web', 'auth', 'console.shell'])->get('/orgs/{org}/home', fn () => Inertia::render('Home'))->name('billing');
    app('router')->getRoutes()->refreshNameLookups();
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)->page('billing', 'Home');

    $this->assertShell($this->get('/orgs/cbox/other/x', inertiaVisit()))
        ->assertPath('nav.activeArea', '');
});

it('filters nav pages per request with the app\'s filter', function (): void {
    $calls = [];
    Console::nav()->area('catalog', 'Catalog', 'tag', 30)
        ->page('billing.drafts', 'Drafts')
        ->page('billing.coupons', 'Coupons')
        ->page('members', 'Members', feature: 'off', lockedWhenOff: true);
    Console::nav()->area('admin', 'Admin', 'shield', 40)->page('settings.project', 'Project settings');

    Console::shell()->navFilter(function (NavPage $page, Request $request, array $context) use (&$calls): bool {
        $calls[] = $page->route.'@'.$context[0]->id;

        return ! in_array($page->route, ['billing.coupons', 'members', 'settings.project'], true);
    });

    $this->assertShell($this->get('/orgs/cbox/drafts', inertiaVisit()))
        ->assertNavArea('catalog')
        // An area left with no pages is left out.
        ->assertNoNavArea('admin');

    expect(array_column(catalogPages('/orgs/cbox/drafts'), 'label'))->toBe(['Drafts'])
        ->and($calls)->toContain('billing.coupons@org_1', 'members@org_1');
});

it('sends the app\'s palette placeholder, and none by default', function (): void {
    $url = '/orgs/cbox/drafts';
    expect($this->get($url, inertiaVisit())->json('props.shell.palette'))->not->toHaveKey('placeholder');

    Console::shell()->palettePlaceholder('Search customers, invoices… or pages');
    expect($this->get($url, inertiaVisit())->json('props.shell.palette.placeholder'))->toBe('Search customers, invoices… or pages');

    Console::shell()->palettePlaceholder(fn (Request $request): string => 'Search in '.$request->route('org'));
    expect($this->get($url, inertiaVisit())->json('props.shell.palette.placeholder'))->toBe('Search in cbox');
});

it('adds a #fragment to a route action', function (): void {
    Console::shell()->actions([
        PaletteAction::route('members', 'Members'),
        PaletteAction::route('members', 'Invite members', icon: 'users', fragment: '#invite'),
        PaletteAction::route('members', 'Roles', fragment: ''),
    ]);

    $actions = $this->get('/orgs/cbox/drafts', inertiaVisit())->json('props.shell.palette.actions');

    expect($actions)->toBe([
        ['id' => 'members', 'label' => 'Members', 'url' => '/orgs/cbox/members'],
        ['id' => 'members#invite', 'label' => 'Invite members', 'url' => '/orgs/cbox/members#invite', 'icon' => 'users'],
        ['id' => 'members', 'label' => 'Roles', 'url' => '/orgs/cbox/members'],
    ]);
});
