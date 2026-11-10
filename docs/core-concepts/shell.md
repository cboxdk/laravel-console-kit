---
title: The shared shell
description: The shell prop field by field, the endpoints behind its menus, the switching rules and the local-first guarantee.
weight: 1
---

# The shared shell

Every Cbox app renders one shell from cbox-ui. The server describes it in one Inertia
prop, `shell`. `Console::shell()` builds that prop; `console.shell` shares it.

## The prop

The PHP value objects under `Cbox\Console\Kit\Shell` mirror cbox-ui's `Shell` type field
for field. `toArray()` is the wire shape. Optional fields are left out, never sent as
`null`.

```ts
type Shell = {
  app: { key: string; name: string; homeUrl: string };
  person: { id: string; name: string; initials: string; avatarUrl?: string };
  nav: { areas: NavArea[]; activeArea: string; activePage?: string };
  context: { levels: ContextLevel[]; switchUrl: string };
  palette: { searchUrl: string; actions: PaletteAction[] };
  help: { enabled: boolean; topic?: string; sessionUrl?: string; portalUrl?: string; updatesUrl?: string };
  notifications: { enabled: false } | { enabled: true; unread: number; url: string };
  apps: { catalogUrl: string };
  banner?: { kind: 'sandbox' | 'impersonation' | 'notice'; text: string; action?: { label: string; href: string } };
  preferences: { theme: 'system' | 'light' | 'dark'; locale: string; railPinned: boolean; helpOpen: boolean };
};

type NavArea = { key: string; label: string; href: string; icon?: string; pages: { key: string; label: string; href: string }[] };

type ContextLevel = {
  key: string; label: string;
  current: { id: string; name: string; initials?: string; tone?: 'production' | 'sandbox' | 'neutral'; label?: string; testData?: boolean };
  listUrl: string; createUrl?: string; allUrl: string;
  settingsUrl: string; // template: replace "{id}" with a row's id
};

type PaletteAction = { id: string; label: string; url: string; icon?: string; keywords?: string[]; shortcut?: string };
```

`NavArea` and `PaletteAction` are not spelled out in the spec; the shapes above are what
the kit sends.

### Where each part comes from

| Part | Source |
|---|---|
| `app` | `->app($key, $name, $homeUrl)`, else `config('app.name')` |
| `person` | `->person(fn)`, else the authenticated user's id, `name` (or `email`), `avatar_url` |
| `nav` | `Console::nav()` areas, pages gated by `Console::features()` and by the person's permission (`can:`, checked with `Gate::forUser($request->user())`). A page whose route cannot be built on this page (a required parameter is missing) is left out; an area with no pages is left out. `href` is the area's first page. |
| `nav.activeArea` | The area holding the current route, else the area holding a page of the same resource (`deployments.show` → `deployments.index`). |
| `context.levels` | Each level's `current()`, outermost first, until one has none. |
| `palette.actions` | `->actions()`. A route action takes the current route's parameters; one that cannot be built here is left out. |
| `help` | `->help(HelpConfig)` and `->helpTopic(fn)`. Disabled without a config. |
| `notifications` | `{enabled: false}` without a source: the bell is not rendered at all. |
| `apps.catalogUrl` | `->appCatalogUrl()`, else laravel-id-client's `cbox-id.apps.catalog`, else `/apps/catalog`, with `?include=available` so the switcher also gets the apps the organization does not have yet. |
| `banner` | `->banner(fn)` wins. Otherwise a sandbox strip when the deepest current item has `testData: true`, with the text from `->sandboxText()`. |
| `preferences` | `ShellPreferenceStore` (cookies by default), the app locale. |

## Lists load lazily

The prop carries only the current selection. Each menu fetches its `listUrl` when it
opens:

```
GET /shell/context/{level}?q=&parent=&cursor=&limit=&ancestors[organization]=org_1
→ {object: "list", data: [...], has_more, next_cursor}
```

`listUrl` already holds the current ancestors. The cascade overrides `parent` with the
hovered project. Every ancestor id is resolved through its level's `find()`; one the
person may not open answers `404`.

## The endpoints

`Console::shellRoutes($prefix = 'shell', $middleware = ['web', 'auth'])` registers:

| Method | Path | Name | Answer |
|---|---|---|---|
| GET, POST | `switch` | `console.shell.switch` | 303 to the target, or `{url, resolution}` for JSON |
| GET | `context/{level}` | `console.shell.context.list` | a list page |
| POST | `context/{level}` | `console.shell.context.create` | `201 {id, detailsUrl}` |
| GET | `context/{level}/{id}/settings` | `console.shell.context.settings` | redirect to the item's settings |
| GET | `search` | `console.shell.search` | `{groups: [{key, label, items}]}` |
| GET | `notifications` | `console.shell.notifications` | `{unread, data}` |
| POST | `notifications/read` | `console.shell.notifications.read` | 204 |
| PATCH | `preferences` | `console.shell.preferences` | 204, with cookies |

`Console::shellHome($uri = '/')` adds `GET /` (`console.shell.home`): see
[The last context](#the-last-context).

## Permissions in the nav

A page or an area can name an ability. It shows only when the person may use it, so the
rail and the palette's Pages never offer a page that answers 403:

```php
Console::nav()->area('settings', 'Settings', 'cog', 90)
    ->page('billing.plan', 'Plan', can: 'manage-billing',
        arguments: fn (Request $request, array $context) => [Organization::find($context[0]->id)]);

Console::nav()->area('admin', 'Admin')->can('admin');   // the whole area
```

`arguments` is a list, or a closure that gets the request and the current context
(`list<ContextItem>`, outermost first). Keep the policy local: it runs on every page.

## Switching

`switch` takes `select` (level key → id) and `from` (the page's path). The URL is the
state; nothing goes into the session.

1. **The new selection.** Selected ids go through `find()`; an unknown id is a `404`.
   Levels above the switched one keep the current item. Levels below keep the item with
   the same name if the new parent has one, else its first item. Switching project keeps
   "Production" when the new project has one.
2. **Same page.** If `from`'s route carries the switched level and has no other required
   parameters, it is rebuilt with the new slugs. Optional parameters (`{tab?}`) and the
   query string are kept.
3. **Entity pages fall back.** A route with a required parameter that is not a context
   level shows one record. It falls back to the list page of that resource: the route
   `->listFallback(fn)` names, else the resource's `.index` route, else the nav page that
   shares its route-name prefix. The record's id is never
   carried into another context.
4. **Otherwise** the person lands on the deepest selected item's own page (`url()`).

`from` must be a same-origin path. Anything else is ignored. The landing URL is saved as
the last context (see below).

Clicking an environment in the cascade posts both levels:
`select[project]=prj_2&select[environment]=env_5`.

The organization's token follows the URL. That is laravel-id-client's job: its
`cbox-id.org-url` middleware re-binds the token when the URL's organization differs
from the token's.

## The last context

Every switch saves where it landed, for this person. `Console::shellHome()` registers
`GET /` to land there:

1. the last context, if the person can still open it (the top level's `current()` still
   resolves that path);
2. else the person's first top-level item's own page (`/orgs/cbox`);
3. else the top level's collection (`/orgs`), where they can create one.

```php
// routes/web.php, in place of your own home route
Console::shellHome();
```

The default store keeps it in an encrypted, HttpOnly cookie with a fingerprint of the
person's id. After "Switch account", the next person in that browser does not inherit it.

## Palette search

`search` answers the server groups of the palette:

- **records**: the app's `SearchProvider`, in the page's context;
- **goto**: the person's top-level items (organizations), plus level 2 and level 2 · 3
  combinations across the organizations they belong to, the current one first. A row's
  hint names its organization ("Project · Acme"). Each row is a plain link to `switch`
  (GET), so Enter and ⌘Enter both work.

"Go to" is bounded so one keystroke costs a fixed number of `list()` calls: level 2 is
searched under at most 10 organizations (`ShellSearch::GOTO_PARENTS`), and only the first
3 level-2 hits are expanded into their environments (`ShellSearch::GOTO_EXPANDED`).

`?group=records` or `?group=goto` answers one group, so the palette can ask in parallel.
A group that throws is reported and left out; the others still answer.

## Local first

Nothing in the prop, a list or a search calls another service. Levels, search and the
bell read the app's own data (its tables, its mirror of Cbox ID). Help, support updates
and docs search load in the browser after the page has painted. If Cbox ID or the
Assistant is down, the shell still renders.

The one exception is "New …": the person clicked it and waits for it, so a level may call
another service there, with a short timeout.

## Preferences before first paint

`CookieShellPreferences` keeps `cbox_theme`, `cbox_shell_rail` and `cbox_shell_help`
cookies, a year long. `PATCH /shell/preferences` writes them. Because the server reads
them, theme, rail pin and Help open are right on first paint.

The service provider registers the three with `EncryptCookies::except`, and they are not
HttpOnly: the values are not secret, and the page can apply a change itself before the
`PATCH` returns. The reader ignores any value it does not know. The last-context cookie
stays encrypted and HttpOnly.

## Blade pages

`console.shell` builds the prop lazily, once per request, and only when something
renders it. With Inertia it is a shared prop. Any Blade view rendered on the request also
gets `$shell` (an array) through a view composer, so a JSON answer or a redirect on the
same route never builds it.
