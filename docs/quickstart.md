---
title: Quickstart
description: Give an app the shared Cbox shell in four steps — describe it, register its routes, share it, and render it with cbox-ui.
weight: 1
---

# Quickstart

This sets up the shared shell: the rail, the header's context selectors, the ⌘K
palette, Help, the bell and the app switcher. cbox-ui renders it; this package builds the
`shell` prop it renders from.

## 1. Describe the shell

In a service provider:

```php
use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\PaletteAction;
use Cbox\Console\Kit\Shell\StaticHelpConfig;
use Cbox\Id\Client\Shell\OrganizationLevel; // from cboxdk/laravel-id-client
use Illuminate\Http\Request;

public function boot(): void
{
    Console::nav()->area('deployments', 'Deployments', 'rocket', 20)
        ->page('deployments.index', 'Deployments')
        ->page('logs.index', 'Logs');

    Console::shell()
        ->app('infra', 'Cbox Infra', '/orgs')
        ->levels(app(OrganizationLevel::class), new ProjectLevel, new EnvironmentLevel)
        ->search(new DeploymentSearch)
        ->actions(fn () => [PaletteAction::route('deployments.create', 'New deployment')])
        ->help(new StaticHelpConfig(sessionUrl: '/assistant/session'))
        ->helpTopic(fn (Request $r) => $r->route()?->getName());
}
```

`ProjectLevel`, `EnvironmentLevel` and `DeploymentSearch` are yours: see
[Context levels and providers](extension-points/context-levels.md).

## 2. Register the shell's routes

```php
// routes/web.php
Console::shellRoutes();
```

## 3. Share the prop on your pages

```php
Route::middleware(['web', 'auth', 'console.shell'])->group(function () {
    Route::get('/orgs/{org}/projects/{project}/environments/{environment}/deployments', ...)
        ->name('deployments.index');
});
```

## 4. Render it

Your persistent Inertia layout passes `usePage().props.shell` to cbox-ui's `CboxShell`.

## Test it

```php
$this->assertShell($this->get('/orgs/cbox/projects/webshop/environments/production/deployments'))
    ->assertLevel('organization', 'org_1')
    ->assertActiveArea('deployments');
```

See [Testing](getting-started/testing.md).
