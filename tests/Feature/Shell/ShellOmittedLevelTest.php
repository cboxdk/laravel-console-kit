<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\PaletteAction;
use Cbox\Console\Kit\Shell\SwitchResult;
use Cbox\Console\Kit\Testing\FakeContextLevel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Pages whose URL omits an outer level (cbox-telemetry's `/projects/{project}/…`, where the
// organization comes from the project), and route parameters that pick a view, not a record.

beforeEach(function (): void {
    shellApp();
    [$this->orgs, $this->projects] = shellLevels();

    // The organization of a `/projects/{project}` page is the project's.
    $owners = ['webshop' => 'org_1', 'api' => 'org_1', 'storefront' => 'org_2'];
    $this->orgs->currentUsing(function (Request $request, array $ancestors, FakeContextLevel $level) use ($owners): ?ContextItem {
        $route = $request->route();
        $org = $route instanceof RoutingRoute ? $route->originalParameter('org') : null;
        $project = $route instanceof RoutingRoute ? $route->originalParameter('project') : null;

        foreach (['org_1' => 'cbox', 'org_2' => 'acme'] as $id => $slug) {
            if ($org === $slug || (is_string($project) && ($owners[$project] ?? null) === $id)) {
                return $level->find($request, $id, []);
            }
        }

        return null;
    });

    Route::middleware(['web', 'auth', 'console.shell'])->group(function (): void {
        $page = static fn (string $component): Closure => static fn () => Inertia::render($component);

        Route::get('/projects/{project}/insights', $page('Insights'))->name('projects.insights');
        Route::get('/projects/{project}/signals/{signal}', $page('Signal'))->name('projects.signals.show');
        Route::get('/orgs/{org}/usage', $page('Usage'))->name('orgs.usage');
    });

    Console::nav()->area('overview')->page('orgs.usage', 'Usage');
    Console::nav()->area('project', 'Project', 'folder', 30)->page('projects.insights', 'Insights');

    $this->shell = $this->shellLevels($this->orgs, $this->projects);
});

it('fills the rail from the resolved context when the page URL omits a level', function (): void {
    $shell = $this->assertShell($this->get('/projects/webshop/insights', inertiaVisit()))
        ->assertLevels(['organization', 'project'])
        ->assertNavArea('overview')
        ->assertNavArea('project')
        ->assertActiveArea('project', 'projects.insights');

    expect(array_column($shell->get('nav.areas.0.pages'), 'href'))->toBe(['/orgs/cbox', '/orgs/cbox/usage'])
        ->and($shell->get('nav.areas.1.href'))->toBe('/projects/webshop/insights');

    // The page's own parameters still win over the context's.
    $this->assertShell($this->get('/orgs/acme/usage', inertiaVisit()))->assertPath('nav.areas.0.href', '/orgs/acme');
});

it('fills palette actions and account links from the context too', function (): void {
    Route::middleware(['web', 'auth'])->get('/orgs/{org}/profile', fn () => 'profile')->name('profile');
    $this->shell->actions([PaletteAction::route('orgs.settings', 'Organization settings')]);

    $this->assertShell($this->get('/projects/webshop/insights', inertiaVisit()))
        ->assertAction('orgs.settings', '/orgs/cbox/settings')
        ->assertPath('account.profileUrl', '/orgs/cbox/profile');
});

it('keeps a page whose required parameter is a view when switching project', function (): void {
    $from = '/projects/webshop/signals/errors';

    // Undeclared, `{signal}` names a record: the switch leaves the page.
    expect(switchTo(['project' => 'prj_2'], $from)['resolution'])->not->toBe(SwitchResult::SAME_PAGE);

    $this->shell->page('projects.signals.*', views: ['signal']);

    expect(switchTo(['project' => 'prj_2'], $from))
        ->toBe(['url' => '/projects/api/signals/errors', 'resolution' => SwitchResult::SAME_PAGE]);
});

it('takes view parameters declared for every route', function (): void {
    $this->shell->viewParameters('signal');

    expect(switchTo(['project' => 'prj_2'], '/projects/webshop/signals/traces?range=1h'))
        ->toBe(['url' => '/projects/api/signals/traces?range=1h', 'resolution' => SwitchResult::SAME_PAGE]);
});

it('switches only the level whose id changed in the body cbox-ui 0.11 posts', function (): void {
    // cbox-ui's switchBody sends the ancestors' current ids along with the new one.
    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_1', 'project' => 'prj_2'], 'from' => '/projects/webshop/insights'])
        ->assertOk()
        ->assertExactJson(['url' => '/projects/api/insights', 'resolution' => SwitchResult::SAME_PAGE]);
});
