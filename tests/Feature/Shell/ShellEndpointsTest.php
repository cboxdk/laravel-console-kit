<?php

declare(strict_types=1);

use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextPage;
use Cbox\Console\Kit\Shell\ContextQuery;
use Cbox\Console\Kit\Shell\CookieShellPreferences;
use Cbox\Console\Kit\Shell\NotificationItem;
use Cbox\Console\Kit\Shell\SearchResult;
use Cbox\Console\Kit\Testing\FakeNotificationSource;
use Cbox\Console\Kit\Testing\FakeSearchProvider;
use Illuminate\Http\Request;

beforeEach(function (): void {
    shellApp();
    [$this->orgs, $this->projects, $this->environments] = shellLevels();
    $this->shellLevels($this->orgs, $this->projects, $this->environments);
});

it('lists a level lazily, paginated and searchable', function (): void {
    $this->getJson('/shell/context/organization')->assertOk()->assertExactJson([
        'object' => 'list',
        'data' => [['id' => 'org_1', 'name' => 'Cbox', 'initials' => 'C'], ['id' => 'org_2', 'name' => 'Acme', 'initials' => 'A']],
        'has_more' => false,
        'next_cursor' => null,
    ])->assertHeader('Cache-Control', 'no-store, private');

    $this->getJson('/shell/context/organization?q=acm')->assertJsonPath('data.0.id', 'org_2')->assertJsonCount(1, 'data');

    $first = $this->getJson('/shell/context/organization?limit=1')->assertJsonPath('has_more', true)->json('next_cursor');
    $this->getJson('/shell/context/organization?limit=1&cursor='.$first)->assertJsonPath('data.0.id', 'org_2')->assertJsonPath('has_more', false);
});

it('lists a deeper level under its ancestors, with parent overriding for the cascade', function (): void {
    $this->getJson('/shell/context/project?ancestors[organization]=org_1')
        ->assertJsonPath('data.*.id', ['prj_1', 'prj_2']);

    // Hovering "API" in the cascade: same organization, parent = the hovered project.
    $this->getJson('/shell/context/environment?ancestors[organization]=org_1&ancestors[project]=prj_1&parent=prj_2')
        ->assertJsonPath('data.*.id', ['env_3']);

    // Environments keep the app's order and carry tone, label and testData.
    $this->getJson('/shell/context/environment?ancestors[organization]=org_1&parent=prj_1')
        ->assertJsonPath('data.1', ['id' => 'env_2', 'name' => 'Sandbox', 'tone' => 'sandbox', 'label' => 'test data', 'testData' => true]);
});

it('refuses ancestors the person may not open, and unknown levels', function (): void {
    $this->getJson('/shell/context/project')->assertNotFound();
    $this->getJson('/shell/context/project?ancestors[organization]=org_404')->assertNotFound();
    // prj_3 is acme's; asking for it under cbox finds nothing.
    $this->getJson('/shell/context/environment?ancestors[organization]=org_1&parent=prj_3')->assertNotFound();
    $this->getJson('/shell/context/galaxy')->assertNotFound();
});

it('creates inline with only a name and answers the details page', function (): void {
    $this->postJson('/shell/context/project?ancestors[organization]=org_1', ['name' => ' Payments '])
        ->assertCreated()
        ->assertExactJson(['id' => 'project_new_1', 'detailsUrl' => '/orgs/cbox/projects/payments']);

    $this->projects->assertCreated('Payments');
    expect($this->projects->created())->toBe([['name' => 'Payments', 'ancestors' => ['org_1']]]);

    // The new project is selected on the next list.
    $this->getJson('/shell/context/project?ancestors[organization]=org_1&q=pay')->assertJsonPath('data.0.id', 'project_new_1');
});

it('validates the name and refuses creating under someone else\'s parent', function (): void {
    $this->postJson('/shell/context/project?ancestors[organization]=org_1', ['name' => ''])->assertUnprocessable();
    $this->postJson('/shell/context/project?ancestors[organization]=org_1', ['name' => str_repeat('x', 121)])->assertUnprocessable();
    $this->postJson('/shell/context/project?ancestors[organization]=org_9', ['name' => 'X'])->assertNotFound();

    $this->projects->assertNothingCreated();
});

it('offers no create for a level that cannot create', function (): void {
    $readOnly = new class implements ContextLevel
    {
        public function key(): string
        {
            return 'organization';
        }

        public function label(): string
        {
            return 'Organization';
        }

        public function routeParameter(): string
        {
            return 'org';
        }

        public function current(Request $request, array $ancestors): ?ContextItem
        {
            return new ContextItem('org_1', 'Cbox', 'cbox');
        }

        public function find(Request $request, string $id, array $ancestors): ?ContextItem
        {
            return null;
        }

        public function list(Request $request, ContextQuery $query): ContextPage
        {
            return new ContextPage;
        }

        public function url(ContextItem $item, array $ancestors): string
        {
            return '/orgs/'.$item->routeKey;
        }

        public function allUrl(array $ancestors): string
        {
            return '/orgs';
        }

        public function settingsUrl(ContextItem $item, array $ancestors): string
        {
            return '/orgs/'.$item->routeKey.'/settings';
        }
    };

    Console::shell()->levels($readOnly);

    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))->assertCanCreate('organization', false);
    $this->postJson('/shell/context/organization', ['name' => 'New'])->assertNotFound();
});

it('resolves the settings gear by id and redirects to the item\'s settings', function (): void {
    $this->get('/shell/context/project/prj_2/settings?ancestors[organization]=org_1')->assertRedirect('/orgs/cbox/projects/api/settings');
    $this->get('/shell/context/project/prj_3/settings?ancestors[organization]=org_1')->assertNotFound();
});

it('answers the palette\'s records in the current context', function (): void {
    $search = (new FakeSearchProvider)->with(
        new SearchResult('dep_1', 'Deploy #41', '/orgs/cbox/projects/webshop/environments/production/deployments/dep_1', 'main'),
        new SearchResult('dep_2', 'Rollback', '/x'),
    );
    Console::shell()->search($search);

    $this->getJson('/shell/search?q=deploy&group=records&context[organization]=org_1&context[project]=prj_1&context[environment]=env_1')
        ->assertOk()
        ->assertExactJson(['groups' => [[
            'key' => 'records', 'label' => 'Records',
            'items' => [['id' => 'dep_1', 'title' => 'Deploy #41', 'url' => '/orgs/cbox/projects/webshop/environments/production/deployments/dep_1', 'subtitle' => 'main']],
        ]]]);

    $search->assertSearched('deploy', ['organization' => 'org_1', 'project' => 'prj_1', 'environment' => 'env_1']);
});

it('never trusts a context id from the query', function (): void {
    $search = new FakeSearchProvider;
    Console::shell()->search($search);

    // prj_3 is not cbox's: the context stops at the organization.
    $this->getJson('/shell/search?q=x&group=records&context[organization]=org_1&context[project]=prj_3')->assertOk();

    $search->assertSearched('x', ['organization' => 'org_1']);
});

it('answers "Go to" with organizations and level 2 · 3 combinations as switch links', function (): void {
    $items = $this->getJson('/shell/search?q=web&group=goto&context[organization]=org_1&from=/orgs/cbox/projects/api/environments/staging/deployments')
        ->assertOk()->json('groups.0.items');

    expect(array_column($items, 'title'))->toBe(['Webshop', 'Webshop · Production', 'Webshop · Sandbox'])
        ->and($items[1]['subtitle'])->toBe('Project · Cbox');

    $this->get($items[1]['url'])->assertRedirect('/orgs/cbox/projects/webshop/environments/production/deployments');

    $orgs = $this->getJson('/shell/search?q=acme&group=goto&from=/orgs/cbox/settings')->json('groups.0.items');
    expect($orgs[0]['title'])->toBe('Acme')->and($orgs[0]['subtitle'])->toBe('Organization');
    $this->get($orgs[0]['url'])->assertRedirect('/orgs/acme/settings');
});

it('answers every server group without a group filter, and leaves out one that fails', function (): void {
    Console::shell()->search((new FakeSearchProvider)->failWith(new RuntimeException('search is down')));

    $groups = $this->getJson('/shell/search?q=cbox')->assertOk()->json('groups');

    expect(array_column($groups, 'key'))->toBe(['goto']);
});

it('answers empty groups without a query', function (): void {
    $search = new FakeSearchProvider;
    Console::shell()->search($search);

    expect($this->getJson('/shell/search')->json('groups'))->toBe([
        ['key' => 'records', 'label' => 'Records', 'items' => []],
        ['key' => 'goto', 'label' => 'Go to', 'items' => []],
    ]);
    $search->assertNotSearched();
});

it('serves the bell and marks items read', function (): void {
    Console::shell()->notifications((new FakeNotificationSource)->with(
        new NotificationItem('n1', 'Deploy finished', new DateTimeImmutable('2026-11-10T10:00:00+01:00'), body: 'Webshop', url: '/orgs/cbox'),
        new NotificationItem('n2', 'Deploy failed', new DateTimeImmutable('2026-11-10T11:00:00Z')),
    ));

    $this->getJson('/shell/notifications')->assertOk()->assertExactJson([
        'unread' => 2,
        'data' => [
            ['id' => 'n2', 'title' => 'Deploy failed', 'body' => null, 'url' => null, 'read' => false, 'created_at' => '2026-11-10T11:00:00Z'],
            ['id' => 'n1', 'title' => 'Deploy finished', 'body' => 'Webshop', 'url' => '/orgs/cbox', 'read' => false, 'created_at' => '2026-11-10T09:00:00Z'],
        ],
    ]);

    $this->postJson('/shell/notifications/read', ['id' => 'n2'])->assertNoContent();
    $this->getJson('/shell/notifications')->assertJsonPath('unread', 1);

    $this->postJson('/shell/notifications/read')->assertNoContent();
    $this->getJson('/shell/notifications')->assertJsonPath('unread', 0);
});

it('has no bell endpoint without a source', function (): void {
    $this->getJson('/shell/notifications')->assertNotFound();
    $this->postJson('/shell/notifications/read')->assertNotFound();
});

it('saves preferences per person and reads them back before first paint', function (): void {
    $response = $this->patchJson('/shell/preferences', ['theme' => 'dark', 'railPinned' => true, 'helpOpen' => false])
        ->assertNoContent()
        ->assertCookie(CookieShellPreferences::THEME, 'dark')
        ->assertCookie(CookieShellPreferences::RAIL, '1')
        ->assertCookie(CookieShellPreferences::HELP, '0');

    $cookie = $response->getCookie(CookieShellPreferences::RAIL, false);
    expect($cookie?->isHttpOnly())->toBeFalse()->and($cookie?->getSameSite())->toBe('lax');

    $this->patchJson('/shell/preferences', ['helpOpen' => true])
        ->assertCookie(CookieShellPreferences::HELP, '1')
        ->assertCookieMissing(CookieShellPreferences::THEME);

    $this->patchJson('/shell/preferences', ['theme' => 'neon'])->assertUnprocessable();
});
