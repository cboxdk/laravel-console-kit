<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\CookieShellPreferences;
use Cbox\Console\Kit\Shell\SwitchResult;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    shellApp();
    [$this->orgs, $this->projects, $this->environments] = shellLevels();
    $this->shellLevels($this->orgs, $this->projects, $this->environments);
});

function switchTo(array $select, string $from): array
{
    return test()->postJson('/shell/switch', ['select' => $select, 'from' => $from])->assertOk()->json();
}

it('stays on the same page when it exists in the new context', function (): void {
    expect(switchTo(['project' => 'prj_2'], '/orgs/cbox/projects/webshop/environments/production/logs'))
        ->toBe(['url' => '/orgs/cbox/projects/api/environments/staging/logs', 'resolution' => SwitchResult::SAME_PAGE]);
});

it('keeps the environment with the same name when switching project', function (): void {
    // acme/storefront has Staging first and Production second: Production is kept.
    expect(switchTo(['organization' => 'org_2'], '/orgs/cbox/projects/webshop/environments/production/deployments')['url'])
        ->toBe('/orgs/acme/projects/storefront/environments/production/deployments');
});

it('takes the first environment when the new project has none with that name', function (): void {
    expect(switchTo(['project' => 'prj_2'], '/orgs/cbox/projects/webshop/environments/sandbox/deployments')['url'])
        ->toBe('/orgs/cbox/projects/api/environments/staging/deployments');
});

it('switches project and environment in one step', function (): void {
    expect(switchTo(['project' => 'prj_1', 'environment' => 'env_2'], '/orgs/cbox/projects/api/environments/staging/deployments')['url'])
        ->toBe('/orgs/cbox/projects/webshop/environments/sandbox/deployments');
});

it('switches the environment alone, keeping organization and project', function (): void {
    expect(switchTo(['environment' => 'env_2'], '/orgs/cbox/projects/webshop/environments/production/logs'))
        ->toBe(['url' => '/orgs/cbox/projects/webshop/environments/sandbox/logs', 'resolution' => SwitchResult::SAME_PAGE]);
});

it('falls back from an entity page to its area list, never carrying the entity id', function (): void {
    $result = switchTo(['project' => 'prj_2'], '/orgs/cbox/projects/webshop/environments/production/deployments/dep_42');

    expect($result)->toBe(['url' => '/orgs/cbox/projects/api/environments/staging/deployments', 'resolution' => SwitchResult::LIST_FALLBACK])
        ->and($result['url'])->not->toContain('dep_42');

    expect(switchTo(['organization' => 'org_2'], '/orgs/cbox/projects/webshop/environments/production/logs/log_7')['url'])
        ->toBe('/orgs/acme/projects/storefront/environments/production/logs');
});

it('falls back to the parent list, never a sibling page that shares its prefix', function (): void {
    Route::middleware(['web', 'auth', 'console.shell'])->group(static function (): void {
        $base = '/orgs/{org}/projects/{project}/environments/{environment}/subscriptions';
        Route::get($base, fn () => 'list')->name('billing.subscriptions');
        Route::get($base.'/dunning', fn () => 'dunning')->name('billing.subscriptions.dunning');
        Route::get($base.'/{subscription}', fn () => 'one')->name('billing.subscriptions.show');
    });
    app('router')->getRoutes()->refreshNameLookups();
    Console::nav()->area('subscriptions', 'Subscriptions', 'repeat', 40)
        ->page('billing.subscriptions.dunning', 'Dunning')
        ->page('billing.subscriptions', 'Subscriptions');

    expect(switchTo(['project' => 'prj_2'], '/orgs/cbox/projects/webshop/environments/production/subscriptions/sub_1'))
        ->toBe(['url' => '/orgs/cbox/projects/api/environments/staging/subscriptions', 'resolution' => SwitchResult::LIST_FALLBACK]);
});

it('uses the app\'s fallback route when it names one', function (): void {
    Console::shell()->listFallback(fn ($route) => $route->getName() === 'logs.show' ? 'deployments.index' : null);

    expect(switchTo(['project' => 'prj_2'], '/orgs/cbox/projects/webshop/environments/production/logs/log_7')['url'])
        ->toBe('/orgs/cbox/projects/api/environments/staging/deployments');
});

it('lands on the item\'s own page when the page does not carry the switched level', function (): void {
    // Organization settings has no project in its URL: switching project goes to the project.
    expect(switchTo(['project' => 'prj_2'], '/orgs/cbox/settings'))
        ->toBe(['url' => '/orgs/cbox/projects/api/environments/staging', 'resolution' => SwitchResult::ITEM_HOME]);

    // From the organization picker, an organization switch lands on its deepest default.
    expect(switchTo(['organization' => 'org_1'], '/orgs')['url'])
        ->toBe('/orgs/cbox/projects/webshop/environments/production');
});

it('keeps an organization page an organization page', function (): void {
    expect(switchTo(['organization' => 'org_2'], '/orgs/cbox/settings'))
        ->toBe(['url' => '/orgs/acme/settings', 'resolution' => SwitchResult::SAME_PAGE]);
});

it('rebuilds a project page with the new organization\'s first project when none shares the name', function (): void {
    expect(switchTo(['organization' => 'org_2'], '/orgs/cbox/projects/webshop')['url'])
        ->toBe('/orgs/acme/projects/storefront');
});

it('refuses an item the person may not open, and an unknown level', function (): void {
    // prj_3 belongs to acme; the page is in cbox.
    $this->postJson('/shell/switch', ['select' => ['project' => 'prj_3'], 'from' => '/orgs/cbox/projects/webshop'])->assertNotFound();
    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_404'], 'from' => '/orgs/cbox'])->assertNotFound();
    $this->postJson('/shell/switch', ['select' => ['galaxy' => 'g_1'], 'from' => '/orgs/cbox'])->assertNotFound();
    $this->postJson('/shell/switch', ['select' => [], 'from' => '/orgs/cbox'])->assertNotFound();
});

it('refuses a deeper switch without the outer context', function (): void {
    $this->postJson('/shell/switch', ['select' => ['project' => 'prj_1'], 'from' => '/orgs'])->assertNotFound();
});

it('never redirects off the site', function (): void {
    // A foreign `from` is ignored as if there were none: the organization's own default.
    expect(switchTo(['organization' => 'org_2'], 'https://evil.example/orgs/cbox/settings'))
        ->toBe(['url' => '/orgs/acme/projects/storefront/environments/staging', 'resolution' => SwitchResult::ITEM_HOME])
        ->and(switchTo(['organization' => 'org_2'], '//evil.example/orgs/cbox/settings')['url'])
        ->toBe('/orgs/acme/projects/storefront/environments/staging')
        ->and(switchTo(['organization' => 'org_2'], '/\\evil.example')['url'])
        ->toBe('/orgs/acme/projects/storefront/environments/staging');
});

it('redirects an Inertia or browser switch with 303, and supports GET links from the palette', function (): void {
    $this->post('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings'], ['X-Inertia' => 'true'])
        ->assertStatus(303)->assertRedirect('/orgs/acme/settings');

    $this->get('/shell/switch?'.http_build_query(['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings']))
        ->assertStatus(303)->assertRedirect('/orgs/acme/settings');
});

it('remembers the landing url as the last context, for this person only', function (): void {
    $response = $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings']);
    $value = (string) $response->getCookie(CookieShellPreferences::LAST_CONTEXT)?->getValue();

    expect($value)->toEndWith('|/orgs/acme/settings')
        ->and($response->getCookie(CookieShellPreferences::LAST_CONTEXT, false)?->isHttpOnly())->toBeTrue();

    $store = Console::shell()->preferenceStore();
    $mine = Request::create('/', 'GET', [], [CookieShellPreferences::LAST_CONTEXT => $value]);
    $mine->setUserResolver(fn () => new GenericUser(['id' => 'usr_1']));
    expect($store->lastContext($mine))->toBe('/orgs/acme/settings');

    // After "Switch account", the next person in this browser does not land on it.
    $theirs = Request::create('/', 'GET', [], [CookieShellPreferences::LAST_CONTEXT => $value]);
    $theirs->setUserResolver(fn () => new GenericUser(['id' => 'usr_2']));
    expect($store->lastContext($theirs))->toBeNull();

    $evil = Request::create('/', 'GET', [], [CookieShellPreferences::LAST_CONTEXT => strstr($value, '|', true).'|//evil.example']);
    $evil->setUserResolver(fn () => new GenericUser(['id' => 'usr_1']));
    expect($store->lastContext($evil))->toBeNull();

    $legacy = Request::create('/', 'GET', [], [CookieShellPreferences::LAST_CONTEXT => '/orgs/acme/settings']);
    $legacy->setUserResolver(fn () => new GenericUser(['id' => 'usr_1']));
    expect($store->lastContext($legacy))->toBeNull();
});

it('keeps an optional parameter and the query string when staying on the same page', function (): void {
    Route::middleware(['web', 'auth'])->get('/orgs/{org}/members/{tab?}', fn () => 'ok')->name('members.index');

    expect(switchTo(['organization' => 'org_2'], '/orgs/cbox/members/invites?sort=name'))
        ->toBe(['url' => '/orgs/acme/members/invites?sort=name', 'resolution' => SwitchResult::SAME_PAGE]);
});

it('falls back to the resource\'s index route before any other page of the resource', function (): void {
    // deployments.create is a nav page listed before the index here; the list still wins.
    Console::nav()->area('deployments')->page('deployments.create', 'New deployment', order: 1);

    expect(switchTo(['project' => 'prj_2'], '/orgs/cbox/projects/webshop/environments/production/deployments/dep_9')['url'])
        ->toBe('/orgs/cbox/projects/api/environments/staging/deployments');
});

it('needs a signed-in person', function (): void {
    app('auth')->forgetGuards();

    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox'])->assertUnauthorized();
});

it('works for an app with one level (Work, Engage)', function (): void {
    Console::shell()->levels($this->orgs);

    $this->assertShell($this->get('/orgs/cbox/settings', inertiaVisit()))->assertLevels(['organization']);

    expect(switchTo(['organization' => 'org_2'], '/orgs/cbox/settings')['url'])->toBe('/orgs/acme/settings')
        ->and(switchTo(['organization' => 'org_2'], '/orgs')['url'])->toBe('/orgs/acme');
});

it('accepts the flat body cbox-ui 0.10.3 and older post, with return_to', function (): void {
    // Deprecated: `{<level>: id, return_to}` is read through 0.2.x so either cbox-ui works.
    $this->postJson('/shell/switch', ['project' => 'prj_2', 'return_to' => '/orgs/cbox/projects/webshop/environments/production/logs'])
        ->assertOk()
        ->assertExactJson(['url' => '/orgs/cbox/projects/api/environments/staging/logs', 'resolution' => SwitchResult::SAME_PAGE]);

    $this->post('/shell/switch', ['organization' => 'org_2', 'return_to' => '/orgs/cbox/settings'], ['X-Inertia' => 'true'])
        ->assertStatus(303)->assertRedirect('/orgs/acme/settings');

    $this->postJson('/shell/switch', ['project' => 'prj_1', 'environment' => 'env_2', 'return_to' => '/orgs/cbox/projects/api/environments/staging/deployments'])
        ->assertOk()->assertJsonPath('url', '/orgs/cbox/projects/webshop/environments/sandbox/deployments');
});

it('prefers select and from over the flat body, and keeps refusing what it refused', function (): void {
    // `select` wins; flat keys beside it are ignored.
    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'organization' => 'org_404', 'from' => '/orgs/cbox/settings', 'return_to' => '/orgs/cbox'])
        ->assertOk()->assertJsonPath('url', '/orgs/acme/settings');

    // An empty `select` is still "nothing to switch to", not a fallback to flat keys.
    $this->postJson('/shell/switch', ['select' => [], 'organization' => 'org_2', 'from' => '/orgs/cbox'])->assertNotFound();

    // Flat keys that name no level, an unknown id, a foreign return_to.
    $this->postJson('/shell/switch', ['galaxy' => 'g_1', 'return_to' => '/orgs/cbox'])->assertNotFound();
    $this->postJson('/shell/switch', ['organization' => 'org_404', 'return_to' => '/orgs/cbox'])->assertNotFound();
    $this->postJson('/shell/switch', ['organization' => 'org_2', 'return_to' => '//evil.example/orgs/cbox/settings'])
        ->assertOk()->assertJsonPath('url', '/orgs/acme/projects/storefront/environments/staging');
});
