---
title: Adopt the plugin pattern (for AI agents)
description: Two copy-paste briefs — make a host console pluggable, and build an optional feature package that lights up on install with zero host edits.
weight: 1
---

# Adopt the plugin pattern (for AI agents)

The pattern has two sides. Use **Prompt A** in a host console (ai-assistant, cortex, a
new SaaS) to open the four hooks. Use **Prompt B** to build an optional feature package
that plugs into any host that adopted the kit. Each is self-contained; every method is
real (`Console::nav/features/slots/context/featureActive/dashboardCard`,
`@consoleSlot`, the `console.feature:` route middleware).

---

## Prompt A — make this console pluggable (host side)

````markdown
# Task: Adopt cbox/laravel-console-kit so optional packages can extend this console

Make this admin console pluggable via `cboxdk/laravel-console-kit`: an installed
feature package should add nav, UI and gates with NO edits here. Do NOT put any
feature logic in the host — only wire the socket and render the registry.

## Steps

### 1. Install
```bash
composer require cboxdk/laravel-console-kit
```

### 2. Seed the host's own nav + bind CurrentContext (a ConsoleServiceProvider)
Move this console's hardcoded nav into the registry, and tell plugins who the current
org/user is by binding `CurrentContext` to an adapter over this app's auth.
```php
use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Contracts\CurrentContext;

public function boot(): void
{
    Console::nav()->area('main', 'Overview', 'home', order: 0)
        ->page('dashboard', 'Dashboard', order: 10);
    // …seed the rest of THIS app's areas/pages here…
}

public function register(): void
{
    $this->app->bind(CurrentContext::class, fn () => new \App\Platform\ConsoleCurrentContext);
}
```
`ConsoleCurrentContext implements CurrentContext` with `organizationId(): ?string`,
`userId(): ?string`, `isAdmin(): bool`, delegating to this app's current user/tenant.

### 3. Render the registry in the console shell (replace the hardcoded menu)
```blade
@foreach (\Cbox\Console\Kit\Facades\Console::nav()->areas() as $area)
  {{-- render $area->label / $area->icon --}}
  @foreach ($area->pages() as $page)
    @if (! $page->feature || \Cbox\Console\Kit\Facades\Console::featureActive($page->feature))
      <a href="{{ route($page->route) }}">{{ $page->label }}</a>
    @endif
  @endforeach
@endforeach
```

### 4. Add slots where plugins may inject content
```blade
@consoleSlot('console.dashboard.cards')   {{-- on the dashboard --}}
@consoleSlot('settings.sections')         {{-- on the settings page --}}
```

## Requirements
- No feature package is referenced by name in the host. The host only renders whatever
  is in the registry. Removing a plugin must leave a clean, working console.
- Keep any app-specific entitlement/soft-lock separate from `feature` (feature = is the
  plugin present; entitlement = is the plan allowed). Don't conflate them.

## Acceptance criteria
- The existing nav renders identically, now sourced from `Console::nav()`.
- `@consoleSlot('console.dashboard.cards')` renders empty with no plugin installed.
- A dummy plugin that calls `Console::nav()->area(...)` and `Console::dashboardCard(...)`
  appears with zero host edits, and disappears cleanly when removed.
````

---

## Prompt B — build a feature plug (package side)

````markdown
# Task: Build an optional feature package that plugs into cbox/laravel-console-kit

Build a package that, when `composer require`d into a console that adopted
`cboxdk/laravel-console-kit`, lights up its whole feature — nav + UI + a dashboard card
— all gated on a live "feature" resolver, with NO edits to the host. Data AND UI ship
in this one package. When the feature's backing service isn't wired, the package adds
nothing.

## Layout
- `composer.json` requires `cboxdk/laravel-console-kit: ^0.2` and auto-discovers a
  `ServiceProvider`. Ship `illuminate/*` and (for UI) `livewire/volt`.
- The provider registers into the four hooks in `boot()`.

## The provider
```php
use Cbox\Console\Kit\Facades\Console;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

final class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'my-feature');
        Volt::mount([__DIR__.'/../resources/views/livewire']);
        $this->loadRoutesFrom(__DIR__.'/../routes/feature.php');

        // Feature is ON only where its backing service is bound — deny-by-default.
        Console::features()->register('my-feature',
            fn (): bool => $this->app->bound(\App\Contracts\FeatureService::class));

        // Nav — pages gated on the feature, so nothing shows unless it's on.
        Console::nav()->area('my-feature', 'My Feature', 'sparkles', order: 70)
            ->page('my-feature.index', 'Overview', feature: 'my-feature', order: 10);

        // Dashboard card — return '' unless the feature is on AND there's data.
        Console::dashboardCard(fn (): string => $this->card(), order: 5);
    }

    private function card(): string
    {
        if (! Console::featureActive('my-feature')) {
            return '';
        }
        $org = Console::context()->organizationId();   // host-agnostic current org
        if ($org === null) {
            return '';
        }
        // Render a view to a plain string. Use the View Factory (not the view() helper)
        // so PHPStan is happy with a dynamic view name.
        return $this->app->make(\Illuminate\Contracts\View\Factory::class)
            ->make('my-feature::components.card', ['org' => $org])->render();
    }
}
```

## Routes — gate them so they don't exist when the feature is off
```php
Route::middleware(['<host-auth-middleware>', 'console.feature:my-feature'])
    ->group(function () {
        Volt::route('/my-feature', 'my-feature.index')->name('my-feature.index');
    });
```

## Requirements
- Everything gates on the feature resolver; the resolver returns false unless the
  backing service is bound. No host edits, ever.
- Resolve the current org via `Console::context()`, never via a host-specific class.
- For dynamic view names, render through `Illuminate\Contracts\View\Factory::make()`.

## Acceptance criteria (write these as tests with testbench)
- `Console::featureActive('my-feature')` is false until the backing service is bound,
  true after.
- The nav area exists and its pages carry `feature: 'my-feature'`.
- `Console::slots()->render(ConsoleManager::DASHBOARD_CARDS)` renders the card when the
  service is bound and the org has data; renders `''` otherwise.
- In tests, after rebinding the service/context, call
  `app()->forgetInstance(ConsoleManager::class)` and `Console::clearResolvedInstances()`
  so the facade doesn't return a stale manager.
````

> **The reference plug** is `cboxdk/laravel-id-billing` (private/commercial): the
> billing console — plan, invoices, usage-vs-limit + a current-plan card — gated on a
> `billing` feature that's active only when the billing client is wired. It's Prompt B
> applied to a real feature.
