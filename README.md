# Cbox Console Kit

**`cboxdk/laravel-console-kit`** — the extension hooks a Cbox admin console exposes so an
optional package can `composer require` in and light up a whole feature — nav, UI and
gates — with **zero edits to the host app**. It's the plug *socket*; a feature package
(billing, and others) is the *plug*.

The same kit is adopted by every console (cbox-id, ai-assistant, cortex, …), so one
plugin works across all of them.

## The four hooks

```php
use Cbox\Console\Kit\Facades\Console;

// 1. Nav — add an area, or add pages to a host area. Gate a page on a feature.
Console::nav()->area('billing', 'Billing', 'card', order: 90)
    ->page('billing.plan', 'Plan', feature: 'billing')
    ->page('billing.invoices', 'Invoices', feature: 'billing');

// 2. Features — deny-by-default; a live resolver decides if it's on.
Console::features()->register('billing', fn () => app()->bound(BillingManagement::class));

// 3. Slots — inject content into an EXISTING page.
Console::slots()->add('settings.sections', 'billing::settings-card');

// 4. Dashboard cards — sugar over the well-known dashboard slot.
Console::dashboardCard('billing::plan-card', order: 10);
```

## Host side

Adopt the kit in your console shell — seed your defaults, render the registry:

```blade
{{-- nav --}}
@foreach (\Cbox\Console\Kit\Facades\Console::nav()->areas() as $area)
    {{-- render $area->label / $area->icon; for each $area->pages() show it unless
        $page->feature and ! Console::featureActive($page->feature) --}}
@endforeach

{{-- slots --}}
@consoleSlot('console.dashboard.cards')
@consoleSlot('settings.sections')
```

Gate a plugin's routes so they don't even exist when its feature is off:

```php
Route::middleware('console.feature:billing')->group(fn () => /* billing routes */);
```

## The shared shell

The kit also builds the `shell` prop every Cbox app renders with cbox-ui: the rail, the
header's context selectors, the ⌘K palette, Help, the bell and the app switcher.

```php
Console::shell()
    ->app('infra', 'Cbox Infra')
    ->levels(app(OrganizationLevel::class), new ProjectLevel, new EnvironmentLevel)
    ->search(new DeploymentSearch)
    ->actions(fn () => [PaletteAction::route('deployments.create', 'New deployment')])
    ->helpTopic(fn (Request $r) => $r->route()?->getName());

// routes/web.php
Console::shellRoutes();
Route::middleware(['web', 'auth', 'console.shell'])->group(/* your pages */);
```

Everything is built from the app's own data; nothing waits on another service. See the
[quickstart](docs/quickstart.md) and [the shared shell](docs/core-concepts/shell.md).

## Why a separate package

The hook contracts must be reusable across consoles, so they can't live inside any one
app or inside the billing plugin. This package is just the shared socket — no feature
logic, no UI of its own. Deny-by-default throughout: an unregistered feature is off, a
gated page is hidden, a guarded route 404s.

## License

MIT © Cbox.
