<?php

declare(strict_types=1);

use Cbox\Console\Kit\Contracts\NavRegistry;
use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\BannerKind;
use Cbox\Console\Kit\Shell\CookieShellPreferences;
use Cbox\Console\Kit\Shell\NotificationItem;
use Cbox\Console\Kit\Shell\PaletteAction;
use Cbox\Console\Kit\Shell\Shell;
use Cbox\Console\Kit\Shell\ShellBanner;
use Cbox\Console\Kit\Shell\ShellBuilder;
use Cbox\Console\Kit\Shell\ShellLink;
use Cbox\Console\Kit\Shell\ShellPerson;
use Cbox\Console\Kit\Shell\StaticHelpConfig;
use Cbox\Console\Kit\Testing\AssertableShell;
use Cbox\Console\Kit\Testing\FakeNotificationSource;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    shellApp();
    $this->shellLevels(...shellLevels())->app('infra', 'Cbox Infra', '/orgs');
});

it('shares the exact wire shape of spec §11.1', function (): void {
    $shell = $this->get('/orgs/cbox/projects/webshop/environments/production/deployments', inertiaVisit())
        ->assertOk()
        ->json('props.shell');

    expect(array_keys($shell))->toBe(['app', 'person', 'nav', 'context', 'palette', 'help', 'notifications', 'apps', 'preferences'])
        ->and($shell['app'])->toBe(['key' => 'infra', 'name' => 'Cbox Infra', 'homeUrl' => '/orgs'])
        ->and($shell['person'])->toBe(['id' => 'usr_1', 'name' => 'Sylvester Damgaard', 'initials' => 'SD', 'avatarUrl' => 'https://cdn.example/sd.png'])
        ->and($shell['context']['switchUrl'])->toBe('/shell/switch')
        ->and($shell['context']['levels'][0])->toBe([
            'key' => 'organization',
            'label' => 'Organization',
            'current' => ['id' => 'org_1', 'name' => 'Cbox', 'initials' => 'C'],
            'listUrl' => '/shell/context/organization',
            'createUrl' => '/shell/context/organization',
            'allUrl' => '/orgs',
            'settingsUrl' => '/shell/context/organization/{id}/settings',
        ])
        ->and($shell['context']['levels'][2]['current'])->toBe(['id' => 'env_1', 'name' => 'Production', 'tone' => 'production', 'label' => 'default'])
        ->and($shell['context']['levels'][2]['listUrl'])->toBe('/shell/context/environment?ancestors%5Borganization%5D=org_1&ancestors%5Bproject%5D=prj_1')
        ->and($shell['context']['levels'][1]['allUrl'])->toBe('/orgs/cbox/projects')
        ->and($shell['help'])->toBe(['enabled' => false])
        ->and($shell['notifications'])->toBe(['enabled' => false])
        ->and($shell['apps'])->toBe(['catalogUrl' => '/apps/catalog?include=available'])
        ->and($shell['preferences'])->toBe(['theme' => 'system', 'locale' => 'en', 'railPinned' => false, 'helpOpen' => false]);

    // The prop carries only the current selection: no lists (spec §11.2).
    expect(json_encode($shell))->not->toContain('Webshop · ')->not->toContain('Acme');
});

it('builds the nav from the registry, filtered by features, with the active area', function (): void {
    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/logs', inertiaVisit()))
        ->assertNavArea('overview')
        ->assertNavArea('deployments')
        ->assertNoNavArea('billing')
        ->assertActiveArea('deployments', 'logs.index')
        ->assertPath('nav.areas.1.href', '/orgs/cbox/projects/webshop/environments/production/deployments')
        ->assertPath('nav.areas.1.icon', 'rocket')
        ->assertPath('nav.areas.1.pages.1', ['key' => 'logs.index', 'label' => 'Logs', 'href' => '/orgs/cbox/projects/webshop/environments/production/logs', 'url' => '/orgs/cbox/projects/webshop/environments/production/logs']);

    Console::features()->register('billing', true);

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))
        ->assertNavArea('billing')
        ->assertActiveArea('overview', 'orgs.show')
        // Pages that need a project cannot be built on an organization page; the area is left out.
        ->assertNoNavArea('deployments');
});

it('leaves out nav pages and areas the person may not open', function (): void {
    $seen = [];
    Gate::define('view-logs', function ($user, string $orgId) use (&$seen): bool {
        $seen[] = $orgId;

        return $orgId === 'org_2';
    });
    Gate::define('manage-billing', fn (): bool => false);

    Console::nav()->area('deployments')->page('logs.index', 'Audit logs', can: 'view-logs',
        arguments: fn (Request $request, array $context): array => [$context[0]->id]);
    Console::nav()->area('admin', 'Admin', 'shield', 95)->can('manage-billing')->page('orgs.settings', 'Admin');

    $cbox = $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/deployments', inertiaVisit()))
        ->assertNavArea('deployments')
        ->assertNoNavArea('admin');
    expect(array_column($cbox->get('nav.areas.1.pages'), 'label'))->toBe(['Deployments', 'Logs']);

    $acme = $this->assertShell($this->get('/orgs/acme/projects/storefront/environments/staging/deployments', inertiaVisit()));
    expect(array_column($acme->get('nav.areas.1.pages'), 'label'))->toBe(['Deployments', 'Logs', 'Audit logs'])
        ->and($seen)->toBe(['org_1', 'org_2']);
});

it('shares $shell with a Blade view, built only when a view renders', function (): void {
    $built = 0;
    Console::shell()->person(function () use (&$built): ShellPerson {
        $built++;

        return new ShellPerson('usr_1', 'Ada Lovelace');
    });

    app('view')->addLocation(__DIR__.'/../../views');

    Route::middleware(['web', 'auth', 'console.shell'])->group(function (): void {
        Route::get('/orgs/{org}/blade', fn () => view('shell-name'));
        Route::get('/orgs/{org}/json', fn () => ['ok' => true]);
    });

    $this->get('/orgs/cbox/blade')->assertOk()->assertSeeText('Ada Lovelace');
    $this->get('/orgs/cbox/json')->assertOk();

    expect($built)->toBe(1);
});

it('marks the list area active on an entity page of the same resource', function (): void {
    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/deployments/dep_9', inertiaVisit()))
        ->assertActiveArea('deployments', 'deployments.index');
});

it('stops the levels where the page is not inside one', function (): void {
    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))
        ->assertLevels(['organization']);

    $this->assertShell($this->get('/orgs', inertiaVisit()))
        ->assertLevels([]);

    $this->assertShell($this->get('/orgs/cbox/projects/webshop', inertiaVisit()))
        ->assertLevels(['organization', 'project']);
});

it('never resolves an item from another organization', function (): void {
    // storefront belongs to acme, not cbox.
    $this->assertShell($this->get('/orgs/cbox/projects/storefront', inertiaVisit()))
        ->assertLevels(['organization']);
});

it('adds the sandbox strip when the current environment has test data', function (): void {
    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/sandbox/deployments', inertiaVisit()))
        ->assertBanner('sandbox', 'You are in Sandbox. Everything here is test data.');

    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/deployments', inertiaVisit()))
        ->assertNoBanner();
});

it('takes the sandbox text from the app, and an app banner wins over the strip', function (): void {
    Console::shell()->sandboxText(fn ($env) => "Test mode: {$env->name}");

    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/sandbox/deployments', inertiaVisit()))
        ->assertBanner('sandbox', 'Test mode: Sandbox');

    Console::shell()->banner(fn () => new ShellBanner(BannerKind::Impersonation, 'You are acting as Ada', new ShellLink('Stop', '/impersonation/stop')));

    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/sandbox/deployments', inertiaVisit()))
        ->assertPath('banner', ['kind' => 'impersonation', 'text' => 'You are acting as Ada', 'action' => ['label' => 'Stop', 'href' => '/impersonation/stop', 'url' => '/impersonation/stop']]);
});

it('resolves palette actions against the current route, and drops ones that cannot be built here', function (): void {
    Console::shell()->actions(fn (Request $r) => [
        PaletteAction::route('deployments.create', 'New deployment', icon: 'plus', keywords: ['deploy']),
        PaletteAction::url('/orgs/cbox/settings?invite=1', 'Invite members'),
        PaletteAction::route('does.not.exist', 'Ghost'),
    ]);

    $shell = $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/deployments', inertiaVisit()))
        ->assertAction('deployments.create', '/orgs/cbox/projects/webshop/environments/production/deployments/create')
        ->assertAction('invite-members', '/orgs/cbox/settings?invite=1');

    expect($shell->get('palette.actions'))->toHaveCount(2)
        ->and($shell->get('palette.actions.0'))->toBe([
            'id' => 'deployments.create', 'label' => 'New deployment',
            'url' => '/orgs/cbox/projects/webshop/environments/production/deployments/create',
            'icon' => 'plus', 'keywords' => ['deploy'],
        ]);

    // On a page without a project the action cannot be built: it is left out, not broken.
    expect($this->assertShell($this->get('/orgs/cbox', inertiaVisit()))->get('palette.actions'))->toHaveCount(1);
});

it('bakes the context into the palette search url', function (): void {
    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/deployments', inertiaVisit()))
        ->assertPath('palette.searchUrl', '/shell/search?'.http_build_query([
            'context' => ['organization' => 'org_1', 'project' => 'prj_1', 'environment' => 'env_1'],
            'from' => '/orgs/cbox/projects/webshop/environments/production/deployments',
        ]));
});

it('enables Help from the app config, with the page topic', function (): void {
    Console::shell()
        ->help(new StaticHelpConfig('/assistant/session', 'https://support.example/cbox', '/assistant/updates'))
        ->helpTopic(fn (Request $r) => $r->route()?->getName());

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))
        ->assertHelpEnabled('orgs.show')
        ->assertPath('help', [
            'enabled' => true, 'topic' => 'orgs.show', 'sessionUrl' => '/assistant/session',
            'portalUrl' => 'https://support.example/cbox', 'updatesUrl' => '/assistant/updates',
        ]);
});

it('renders no bell without a source, and the unread count with one', function (): void {
    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))->assertNotificationsDisabled();

    Console::shell()->notifications((new FakeNotificationSource)->with(
        new NotificationItem('n1', 'Deploy finished', new DateTimeImmutable('2026-11-10T10:00:00Z')),
        new NotificationItem('n2', 'Deploy failed', new DateTimeImmutable('2026-11-10T11:00:00Z')),
        new NotificationItem('n3', 'Old', new DateTimeImmutable('2026-11-01T11:00:00Z'), read: true),
    ));

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))
        ->assertNotificationsEnabled(2)
        ->assertPath('notifications.url', '/shell/notifications');
});

it('reads the per-person preferences from cookies before first paint', function (): void {
    // The page writes these itself, so they are plain (not encrypted) cookies.
    $shell = $this->withUnencryptedCookies([
        CookieShellPreferences::THEME => 'dark',
        CookieShellPreferences::RAIL => '1',
        CookieShellPreferences::HELP => '1',
    ])->get('/orgs/cbox', inertiaVisit())->json('props.shell.preferences');

    expect($shell)->toBe(['theme' => 'dark', 'locale' => 'en', 'railPinned' => true, 'helpOpen' => true]);
});

it('ignores a preference cookie it does not recognise', function (): void {
    $shell = $this->withUnencryptedCookies([CookieShellPreferences::THEME => 'neon'])
        ->get('/orgs/cbox', inertiaVisit())->json('props.shell.preferences');

    expect($shell['theme'])->toBe('system');
});

it('uses laravel-id-client\'s catalog route when the app registered it', function (): void {
    Route::get('/apps/x-catalog', fn () => [])->name('cbox-id.apps.catalog');

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))->assertPath('apps.catalogUrl', '/apps/x-catalog?include=available');

    Console::shell()->appCatalogUrl('/custom/catalog');

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))->assertPath('apps.catalogUrl', '/custom/catalog');
});

it('takes the person from the app when it says so', function (): void {
    Console::shell()->person(fn () => new ShellPerson('usr_9', 'Ada Lovelace'));

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))
        ->assertPath('person', ['id' => 'usr_9', 'name' => 'Ada Lovelace', 'initials' => 'AL']);
});

it('refuses to build without the shell routes, with a message that says what to do', function (): void {
    $builder = new ShellBuilder(app(NavRegistry::class), Console::features(), new Router(app('events'), app()), new CookieShellPreferences, app(GateContract::class));

    $builder->build(Request::create('/'));
})->throws(LogicException::class, 'Console::shellRoutes()');

it('limits a shell to three unique levels', function (): void {
    [$a, $b, $c] = shellLevels();

    expect(fn () => Console::shell()->levels($a, $b, $c, $a))->toThrow(InvalidArgumentException::class, 'at most three')
        ->and(fn () => Console::shell()->levels($a, $a))->toThrow(InvalidArgumentException::class, 'unique');
});

it('serialises an empty shell, so a test can build one without the builder', function (): void {
    expect((new Shell)->toArray())->toBe([
        'app' => ['key' => 'app', 'name' => 'App', 'homeUrl' => '/'],
        'person' => ['id' => '', 'name' => '', 'initials' => ''],
        'nav' => ['areas' => [], 'activeArea' => ''],
        'context' => ['levels' => [], 'switchUrl' => ''],
        'palette' => ['searchUrl' => '', 'actions' => []],
        'help' => ['enabled' => false],
        'notifications' => ['enabled' => false],
        'apps' => ['catalogUrl' => '/apps/catalog'],
        'preferences' => ['theme' => 'system', 'locale' => 'en', 'railPinned' => false, 'helpOpen' => false],
    ]);

    AssertableShell::of(new Shell)->assertLevels([])->assertNotificationsDisabled()->assertNoBanner();
});

it('computes initials from a name', function (string $name, string $initials): void {
    expect(ShellPerson::initialsOf($name))->toBe($initials);
})->with([
    ['Sylvester Damgaard', 'SD'],
    ['Cbox', 'C'],
    ['ada.lovelace@example.com', 'AC'],
    ['  ', ''],
    ['Åse Øster Ærø', 'ÅÆ'],
]);
