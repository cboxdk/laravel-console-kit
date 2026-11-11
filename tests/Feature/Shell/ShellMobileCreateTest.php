<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\PaletteAction;
use Cbox\Console\Kit\Shell\PaletteRecord;
use Cbox\Console\Kit\Shell\ShellCreatable;
use Cbox\Console\Kit\Shell\ShellHelpArticle;
use Cbox\Console\Kit\Shell\StaticHelpConfig;
use Cbox\Console\Kit\Shell\Tone;
use Cbox\Console\Kit\Testing\FakeContextLevel;
use Illuminate\Http\Request;

// cbox-ui 0.11: the V6 phone shell and the V7 create sheet.

beforeEach(function (): void {
    shellApp();
    [$this->orgs, $this->projects, $this->environments] = shellLevels();
    $this->shell = $this->shellLevels($this->orgs, $this->projects, $this->environments)->app('infra', 'Cbox Infra', '/orgs');
});

const DEPLOYMENTS = '/orgs/cbox/projects/webshop/environments/production/deployments';

it('sends none of the new keys when the app declares none', function (): void {
    $shell = $this->get(DEPLOYMENTS, inertiaVisit())->json('props.shell');

    expect($shell)->not->toHaveKey('page')->not->toHaveKey('create')
        ->and($shell['nav'])->not->toHaveKey('tabs')
        ->and($shell['palette'])->not->toHaveKey('recent')
        ->and($shell['help'])->toBe(['enabled' => false]);
});

it('says per environment whether it is live, its group and a second line', function (): void {
    $environments = FakeContextLevel::make('environment', 'Environment', 'environment', 'environments', $this->projects)
        ->with(new ContextItem('env_1', 'Production', 'production', tone: Tone::Production, live: true, group: 'Live', description: 'Live customers'), 'prj_1')
        ->with(new ContextItem('env_9', 'Preview', 'preview', tone: Tone::Preview, live: false, group: 'Preview', description: 'From branch feat/quotes'), 'prj_1')
        ->with(new ContextItem('env_8', 'Dev', 'dev', tone: Tone::Development), 'prj_1')
        ->with(new ContextItem('env_7', 'Staging', 'staging', tone: Tone::Staging, live: false), 'prj_1');
    $this->shellLevels($this->orgs, $this->projects, $environments);

    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/preview/deployments', inertiaVisit()))
        ->assertPath('context.levels.2.current', [
            'id' => 'env_9', 'name' => 'Preview', 'tone' => 'preview', 'live' => false, 'group' => 'Preview', 'description' => 'From branch feat/quotes',
        ]);

    $rows = $this->getJson('/shell/context/environment?ancestors[organization]=org_1&ancestors[project]=prj_1')->assertOk()->json('data');

    expect($rows[0])->toBe(['id' => 'env_1', 'name' => 'Production', 'tone' => 'production', 'live' => true, 'group' => 'Live', 'description' => 'Live customers'])
        ->and($rows[2])->toBe(['id' => 'env_8', 'name' => 'Dev', 'tone' => 'development'])
        ->and($rows[3]['tone'])->toBe('staging');
});

it('sends the phone tab bar, leaving out areas the page cannot show', function (): void {
    Console::features()->register('billing', true);
    $this->shell->tabs('deployments', 'billing', 'overview');

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))->assertTabs(['deployments', 'billing', 'overview']);

    // On an organization page the deployments area cannot be built, so it is not a tab there.
    $this->assertShell($this->get('/orgs/cbox', inertiaVisit()))->assertTabs(['billing', 'overview']);
});

it('refuses more than four tabs, or one twice', function (): void {
    expect(fn () => $this->shell->tabs('a', 'b', 'c', 'd', 'e'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->shell->tabs('a', 'a'))->toThrow(InvalidArgumentException::class);
});

it('sends what can be created, and the default when it is one of them', function (): void {
    $this->shell->creatables([
        new ShellCreatable('deployment', 'Deployment', 'deployments', ['deploy', 'ship']),
        new ShellCreatable('log-drain', 'Log drain', 'deployments', submitLabel: 'Add drain'),
    ], default: 'deployment');

    $shell = $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))
        ->assertCanCreateItem('deployment')
        ->assertCanCreateItem('invoice', false)
        ->assertPath('create', [
            'items' => [
                ['key' => 'deployment', 'label' => 'Deployment', 'area' => 'deployments', 'keywords' => ['deploy', 'ship']],
                ['key' => 'log-drain', 'label' => 'Log drain', 'area' => 'deployments', 'submitLabel' => 'Add drain'],
            ],
            'default' => 'deployment',
        ])
        // With a "+" the page carries its title, from the nav page, for "On {page}".
        ->assertPath('page', ['title' => 'Deployments', 'route' => 'deployments.index']);

    expect(array_keys($shell->toArray()))->toBe(['app', 'person', 'page', 'nav', 'context', 'palette', 'create', 'help', 'notifications', 'apps', 'preferences']);

    // A default that is not a creatable is not sent: "+" never opens a missing form.
    $this->shell->creatables([new ShellCreatable('deployment', 'Deployment', 'deployments')], default: 'invoice');
    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))->assertPath('create.default', null);
});

it('reads the creatables per request, and sends no "+" without any', function (): void {
    $this->shell->creatables(fn (Request $request, array $context): array => $context[0]->id === 'org_1'
        ? [new ShellCreatable('deployment', 'Deployment', 'deployments')]
        : []);

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))->assertCanCreateItem('deployment');

    $acme = $this->get('/orgs/acme/projects/storefront/environments/staging/deployments', inertiaVisit())->json('props.shell');
    expect($acme)->not->toHaveKey('create')->not->toHaveKey('page');
});

it('describes a page: its create target, its actions and its title', function (): void {
    $this->shell
        ->creatables([new ShellCreatable('deployment', 'Deployment', 'deployments')])
        ->page('deployments.index', create: 'deployment', actions: [
            PaletteAction::route('deployments.create', 'New deployment', hint: 'Ship the current branch', create: 'deployment'),
            PaletteAction::route('logs.index', 'Open logs')->withHint('Of this environment'),
            PaletteAction::route('projects.missing', 'Never built'),
        ]);

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))
        ->assertPageCreates('deployment')
        ->assertPageAction('deployments.create', DEPLOYMENTS.'/create')
        ->assertPath('page.actions', [
            ['id' => 'deployments.create', 'label' => 'New deployment', 'url' => DEPLOYMENTS.'/create', 'hint' => 'Ship the current branch', 'create' => 'deployment'],
            ['id' => 'logs.index', 'label' => 'Open logs', 'url' => '/orgs/cbox/projects/webshop/environments/production/logs', 'hint' => 'Of this environment'],
        ])
        ->assertPath('page.title', 'Deployments');

    // Another page has no create target of its own: "+" uses create.default (absent here).
    $this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/logs', inertiaVisit()))
        ->assertPageCreates(null)
        ->assertPath('page.title', 'Logs');
});

it('drops a page create key that is not a creatable on this request', function (): void {
    $this->shell->page('deployments.index', create: 'deployment');

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))
        ->assertPath('page', ['title' => 'Deployments', 'route' => 'deployments.index']);
});

it('matches pages by pattern, an exact name first, with a title per request', function (): void {
    $this->shell
        ->page('deployments.*', title: 'Any deployment page')
        ->page('deployments.show', title: fn (Request $request): string => 'Deployment '.$request->route('deployment'));

    $this->assertShell($this->get(DEPLOYMENTS.'/dep_9', inertiaVisit()))->assertPath('page.title', 'Deployment dep_9');
    $this->assertShell($this->get(DEPLOYMENTS.'/create', inertiaVisit()))->assertPath('page.title', 'Any deployment page');
    expect($this->get('/orgs/cbox', inertiaVisit())->json('props.shell'))->not->toHaveKey('page');
});

it('sends the records the palette suggests before anything is typed, same-origin only', function (): void {
    $this->shell->recent(fn (Request $request, array $context): array => [
        new PaletteRecord('dep_9', 'Deployment #9', DEPLOYMENTS.'/dep_9', 'Webshop · '.$context[2]->name),
        new PaletteRecord('evil', 'Elsewhere', 'https://evil.example/x'),
    ]);

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))
        ->assertPath('palette.recent', [['id' => 'dep_9', 'title' => 'Deployment #9', 'url' => DEPLOYMENTS.'/dep_9', 'subtitle' => 'Webshop · Production']]);
});

it('sends help articles for the page topic and the unread count it already knows', function (): void {
    $topics = [];
    $this->shell
        ->help(new StaticHelpConfig(sessionUrl: '/assistant/session'))
        ->helpTopic(fn (Request $request): ?string => $request->route()?->getName())
        ->helpArticles(function (Request $request, ?string $topic) use (&$topics): array {
            $topics[] = $topic;

            return [new ShellHelpArticle('a_1', 'Deploying', excerpt: 'How a deployment runs', url: '/docs/deploying')];
        })
        ->helpUnread(fn (): int => 2);

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))
        ->assertPath('help', [
            'enabled' => true,
            'topic' => 'deployments.index',
            'sessionUrl' => '/assistant/session',
            'articles' => [['id' => 'a_1', 'title' => 'Deploying', 'excerpt' => 'How a deployment runs', 'url' => '/docs/deploying']],
            'unread' => 2,
        ]);

    expect($topics)->toBe(['deployments.index']);

    // No unread replies: no dot.
    $this->shell->helpUnread(fn (): int => 0);
    $this->freshShellRequest();
    expect($this->get(DEPLOYMENTS, inertiaVisit())->json('props.shell.help'))->not->toHaveKey('unread');
});

it('resolves the app catalog per request, over the context', function (): void {
    $this->shell->appCatalogUrl(fn (Request $request, array $context): ?string => isset($context[0])
        ? '/apps/catalog?include=available&organization='.$context[0]->routeKey
        : null);

    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))->assertPath('apps.catalogUrl', '/apps/catalog?include=available&organization=cbox');
    $this->assertShell($this->get('/orgs', inertiaVisit()))->assertPath('apps.catalogUrl', '/apps/catalog?include=available');

    // Never another origin through a protocol-relative URL; a fixed string still works.
    $this->shell->appCatalogUrl(fn (): string => '//evil.example/apps');
    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))->assertPath('apps.catalogUrl', '/apps/catalog?include=available');

    $this->shell->appCatalogUrl('https://id.cbox.dk/apps/catalog');
    $this->assertShell($this->get(DEPLOYMENTS, inertiaVisit()))->assertPath('apps.catalogUrl', 'https://id.cbox.dk/apps/catalog');
});

it('builds palette actions with hint and create, and keeps them through resolve', function (): void {
    $action = PaletteAction::url('/orgs/cbox/invoices/create', 'New invoice', hint: 'Draft for a customer', create: 'invoice');

    expect($action->toArray())->toBe(['id' => 'new-invoice', 'label' => 'New invoice', 'url' => '/orgs/cbox/invoices/create', 'hint' => 'Draft for a customer', 'create' => 'invoice'])
        ->and($action->withCreate(null)->withHint(null)->toArray())->toBe(['id' => 'new-invoice', 'label' => 'New invoice', 'url' => '/orgs/cbox/invoices/create']);
});
