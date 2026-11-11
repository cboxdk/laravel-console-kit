<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextQuery;
use Cbox\Console\Kit\Shell\SearchQuery;
use Cbox\Console\Kit\Shell\SearchResult;
use Cbox\Console\Kit\Shell\Shell;
use Cbox\Console\Kit\Shell\ShellNotifications;
use Cbox\Console\Kit\Testing\AssertableShell;
use Cbox\Console\Kit\Testing\FakeSearchProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\AssertionFailedError;

it('scopes a fake level\'s items to their parent', function (): void {
    [$orgs, $projects] = shellLevels();
    $cbox = $orgs->find(Request::create('/'), 'org_1', []);

    expect($cbox)->toBeInstanceOf(ContextItem::class)
        ->and($projects->find(Request::create('/'), 'prj_3', [$cbox]))->toBeNull()
        ->and($projects->list(Request::create('/'), new ContextQuery(null, [$cbox]))->items)->toHaveCount(2)
        ->and($projects->list(Request::create('/'), new ContextQuery('WEB', [$cbox]))->items[0]->id)->toBe('prj_1');
});

it('pages a fake level with an opaque cursor', function (): void {
    [$orgs] = shellLevels();

    $first = $orgs->list(Request::create('/'), new ContextQuery(limit: 1));
    $second = $orgs->list(Request::create('/'), new ContextQuery(cursor: $first->nextCursor, limit: 1));

    expect($first->items[0]->id)->toBe('org_1')->and($first->nextCursor)->toBe('1')
        ->and($second->items[0]->id)->toBe('org_2')->and($second->nextCursor)->toBeNull()
        ->and($second->toArray()['has_more'])->toBeFalse();
});

it('builds URL-standard urls for a fake level, overridable', function (): void {
    [$orgs, $projects, $environments] = shellLevels();
    $cbox = new ContextItem('org_1', 'Cbox', 'cbox');
    $webshop = new ContextItem('prj_1', 'Webshop', 'webshop');

    expect($orgs->url($cbox, []))->toBe('/orgs/cbox')
        ->and($orgs->allUrl([]))->toBe('/orgs')
        ->and($projects->allUrl([$cbox]))->toBe('/orgs/cbox/projects')
        ->and($environments->settingsUrl(new ContextItem('env_1', 'Production', 'production'), [$cbox, $webshop]))
        ->toBe('/orgs/cbox/projects/webshop/environments/production/settings');

    $orgs->urls(fn (ContextItem $item) => '/teams/'.$item->id, fn () => '/teams');

    expect($orgs->url($cbox, []))->toBe('/teams/org_1')->and($orgs->allUrl([]))->toBe('/teams');
});

it('fails assertCreated when nothing by that name was created', function (): void {
    [$orgs] = shellLevels();

    $orgs->assertCreated('Nope');
})->throws(AssertionFailedError::class, 'Nothing named [Nope]');

it('records and asserts fake searches', function (): void {
    $search = (new FakeSearchProvider)->with(new SearchResult('r1', 'Invoice 1', '/i/1'), new SearchResult('r2', 'Invoice 2', '/i/2'));

    expect($search->search(Request::create('/'), new SearchQuery('invoice', limit: 1)))->toHaveCount(1)
        ->and($search->queries())->toHaveCount(1);

    $search->assertSearched('invoice', []);

    expect(fn () => $search->assertSearched('other'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $search->assertNotSearched())->toThrow(AssertionFailedError::class);
});

it('fails shell assertions with a clear message', function (): void {
    $shell = AssertableShell::of(new Shell(notifications: ShellNotifications::enabled(3, '/n')));

    $shell->assertNotificationsEnabled(3)->assertHelpDisabled()->assertPath('notifications.url', '/n');

    expect(fn () => $shell->assertNotificationsDisabled())->toThrow(AssertionFailedError::class, 'The bell is shown')
        ->and(fn () => $shell->assertLevel('organization'))->toThrow(AssertionFailedError::class, 'no [organization] level')
        ->and(fn () => $shell->assertAction('x'))->toThrow(AssertionFailedError::class, 'no [x] action')
        ->and(fn () => $shell->assertNavArea('x'))->toThrow(AssertionFailedError::class);
});

it('reads the shell from a first Inertia visit (the root view)', function (): void {
    shellApp();
    $this->shellLevels(...shellLevels());
    config(['inertia.root_view' => 'app']);
    app('view')->addLocation(__DIR__.'/../../views');

    $this->assertShell($this->get('/orgs/cbox'))->assertLevel('organization', 'org_1');
});

it('starts the next request with fresh scoped instances', function (): void {
    shellApp();
    $this->shellLevels(...shellLevels());
    $answers = 0;
    // A per-request access check, as an app binds one: its first answer sticks within one test.
    app()->scoped('access.answer', function () use (&$answers): string {
        return 'answer '.++$answers;
    });
    Console::nav()->area('reports', 'Reports', 'chart', 50)
        ->page('logs.index', 'Logs', count: fn (): int => (int) substr((string) app('access.answer'), 7));

    $count = fn (): mixed => collect($this->get('/orgs/cbox/projects/webshop/environments/production/logs', inertiaVisit())->json('props.shell.nav.areas'))
        ->firstWhere('key', 'reports')['pages'][0]['count'];

    expect($count())->toBe(1)
        ->and($count())->toBe(1)
        ->and($this->freshShellRequest())->toBe($this)
        ->and($count())->toBe(2);
});
