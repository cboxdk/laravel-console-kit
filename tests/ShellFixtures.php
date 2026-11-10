<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\Tone;
use Cbox\Console\Kit\Testing\FakeContextLevel;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * The fixture every shell test uses: two organizations, projects and environments, and
 * an app whose routes follow the URL standard.
 *
 * @return array{0: FakeContextLevel, 1: FakeContextLevel, 2: FakeContextLevel}
 */
function shellLevels(): array
{
    $orgs = FakeContextLevel::make('organization', 'Organization', 'org')
        ->with(new ContextItem('org_1', 'Cbox', 'cbox', 'C'))
        ->with(new ContextItem('org_2', 'Acme', 'acme', 'A'));

    $projects = FakeContextLevel::make('project', 'Project', 'project', 'projects', $orgs)
        ->with(new ContextItem('prj_1', 'Webshop', 'webshop', 'W'), 'org_1')
        ->with(new ContextItem('prj_2', 'API', 'api'), 'org_1')
        ->with(new ContextItem('prj_3', 'Storefront', 'storefront'), 'org_2');

    $environments = FakeContextLevel::make('environment', 'Environment', 'environment', 'environments', $projects)
        ->with(new ContextItem('env_1', 'Production', 'production', tone: Tone::Production, label: 'default'), 'prj_1')
        ->with(new ContextItem('env_2', 'Sandbox', 'sandbox', tone: Tone::Sandbox, label: 'test data', testData: true), 'prj_1')
        ->with(new ContextItem('env_3', 'Staging', 'staging', tone: Tone::Neutral), 'prj_2')
        ->with(new ContextItem('env_4', 'Staging', 'staging', tone: Tone::Neutral), 'prj_3')
        ->with(new ContextItem('env_5', 'Production', 'production', tone: Tone::Production), 'prj_3');

    return [$orgs, $projects, $environments];
}

/** The app's page routes, the shell endpoints, and a signed-in person. */
function shellApp(): void
{
    $page = static fn (string $component): Closure => static fn () => Inertia::render($component);

    Route::middleware(['web', 'auth', 'console.shell'])->group(static function () use ($page): void {
        Route::get('/orgs', $page('Orgs'))->name('orgs.index');
        Route::get('/orgs/{org}', $page('Org'))->name('orgs.show');
        Route::get('/orgs/{org}/settings', $page('OrgSettings'))->name('orgs.settings');
        Route::get('/orgs/{org}/projects', $page('Projects'))->name('projects.index');
        Route::get('/orgs/{org}/projects/{project}', $page('Project'))->name('projects.show');
        Route::get('/orgs/{org}/projects/{project}/environments/{environment}', $page('Environment'))->name('environments.show');
        Route::get('/orgs/{org}/projects/{project}/environments/{environment}/deployments', $page('Deployments'))->name('deployments.index');
        Route::get('/orgs/{org}/projects/{project}/environments/{environment}/deployments/create', $page('NewDeployment'))->name('deployments.create');
        Route::get('/orgs/{org}/projects/{project}/environments/{environment}/deployments/{deployment}', $page('Deployment'))->name('deployments.show');
        Route::get('/orgs/{org}/projects/{project}/environments/{environment}/logs', $page('Logs'))->name('logs.index');
        Route::get('/orgs/{org}/projects/{project}/environments/{environment}/logs/{log}', $page('Log'))->name('logs.show');
    });

    Console::shellRoutes();

    Console::nav()->area('overview', 'Overview', 'home', 10)->page('orgs.show', 'Overview');
    Console::nav()->area('deployments', 'Deployments', 'rocket', 20)
        ->page('deployments.index', 'Deployments')
        ->page('logs.index', 'Logs');
    Console::nav()->area('billing', 'Billing', 'card', 90)->feature('billing')->page('orgs.settings', 'Billing');

    test()->actingAs(new GenericUser(['id' => 'usr_1', 'name' => 'Sylvester Damgaard', 'avatar_url' => 'https://cdn.example/sd.png']));
}

/** An Inertia visit, so the response is the page JSON with its props. */
function inertiaVisit(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'];
}
