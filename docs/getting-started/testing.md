---
title: Testing
description: Fake context levels, search and notifications, and assert the shell prop a page shares.
weight: 4
---

# Testing

The kit ships the fakes its own suite uses, under `Cbox\Console\Kit\Testing`.

## Compose the trait

```php
use Cbox\Console\Kit\Testing\InteractsWithShell;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithShell;
}
```

It gives you `shellLevels(...)` (sets the levels on the shell) and
`assertShell($response)`.

## Fake levels

`FakeContextLevel` keeps items in memory, scoped to a parent, and builds URLs by the URL
standard (`/orgs/{slug}`, then `/{segment}/{slug}` per level):

```php
$orgs = FakeContextLevel::make('organization', 'Organization', 'org')
    ->with(new ContextItem('org_1', 'Cbox', 'cbox'));

$projects = FakeContextLevel::make('project', 'Project', 'project', 'projects', parent: $orgs)
    ->with(new ContextItem('prj_1', 'Webshop', 'webshop'), parent: 'org_1');

$this->shellLevels($orgs, $projects);
```

It implements "New …" too. `created()` lists what was created; `assertCreated('Payments')`
and `assertNothingCreated()` assert it.

## Fake search and bell

```php
$search = (new FakeSearchProvider)->with(new SearchResult('dep_1', 'Deploy #41', '/…'));
Console::shell()->search($search);

// …
$search->assertSearched('deploy', ['organization' => 'org_1', 'project' => 'prj_1']);
```

`failWith($exception)` makes every search throw, to prove the palette survives a
failing group. `FakeNotificationSource` holds `NotificationItem`s and marks them read.

## Assert the prop

`assertShell()` reads `shell` from an Inertia visit (`X-Inertia`), a first Inertia
visit (the root view's `page`), or a Blade view's `$shell`:

```php
$this->assertShell($this->get('/orgs/cbox'))
    ->assertApp('infra')
    ->assertLevels(['organization'])
    ->assertLevel('organization', 'org_1')
    ->assertCanCreate('organization')
    ->assertNavArea('overview')
    ->assertActiveArea('overview', 'orgs.show')
    ->assertAction('deployments.create')
    ->assertHelpEnabled('orgs.show')
    ->assertNotificationsDisabled()
    ->assertNoBanner()
    ->assertPath('preferences.theme', 'system');
```

`AssertableShell::of($shell)` takes a built `Shell` or an array directly.
