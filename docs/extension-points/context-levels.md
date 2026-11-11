---
title: Context levels and providers
description: Implement a context level, inline create, records search, a notification source, Help config and a preference store.
weight: 1
---

# Context levels and providers

All of these answer from the app's own data. None may call another service, except
`create()`, which runs when the person clicks "New …".

## ContextLevel

One level of the header's selectors. An app declares one to three, outermost first.

```php
use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextPage;
use Cbox\Console\Kit\Shell\ContextQuery;
use Illuminate\Http\Request;

final class ProjectLevel implements ContextLevel
{
    public function key(): string { return 'project'; }

    public function label(): string { return 'Project'; }

    public function routeParameter(): string { return 'project'; }

    public function current(Request $request, array $ancestors): ?ContextItem
    {
        $slug = $request->route()?->originalParameter('project');

        return $slug === null ? null : $this->find($request, $slug, $ancestors, bySlug: true);
    }

    public function find(Request $request, string $id, array $ancestors, bool $bySlug = false): ?ContextItem
    {
        $project = Project::query()
            ->where('organization_id', $ancestors[0]->id)       // under the parent
            ->whereMemberIs($request->user())                     // the person may open it
            ->where($bySlug ? 'slug' : 'public_id', $id)
            ->first();

        return $project ? $this->item($project) : null;
    }

    public function list(Request $request, ContextQuery $query): ContextPage
    {
        $page = Project::query()
            ->where('organization_id', $query->parent()?->id)
            ->whereMemberIs($request->user())
            ->when($query->search, fn ($q, $s) => $q->whereLike('name', "%{$s}%"))
            ->orderBy('name')
            ->cursorPaginate($query->limit, cursor: $query->cursor);

        return new ContextPage(
            array_map($this->item(...), $page->items()),
            $page->nextCursor()?->encode(),
        );
    }

    public function url(ContextItem $item, array $ancestors): string
    {
        return route('projects.show', ['org' => $ancestors[0]->routeKey, 'project' => $item->routeKey], false);
    }

    public function allUrl(array $ancestors): string
    {
        return route('projects.index', ['org' => $ancestors[0]->routeKey], false);
    }

    public function settingsUrl(ContextItem $item, array $ancestors): string
    {
        return route('projects.settings', ['org' => $ancestors[0]->routeKey, 'project' => $item->routeKey], false);
    }

    private function item(Project $p): ContextItem
    {
        return new ContextItem($p->public_id, $p->name, routeKey: $p->slug);
    }
}
```

Rules:

- **`find()` and `list()` decide access.** The kit resolves every id a client sends
  through `find()` and refuses it when `find()` answers null. Return only what the person
  may open, under the given ancestors.
- **`routeKey`** is what goes in the URL (the slug). It never leaves the server.
- **Environments are app-defined.** Set `tone` (`Tone::Production`, `Sandbox`, `Neutral`,
  and for cbox-ui 0.11 `Staging`, `Preview`, `Development`), a short `label` ("default",
  "test data") and `testData`. The shell does not sort; return items in the order the
  person should see. `testData: true` on the current environment shows the sandbox strip.
- **For the phone's environment picker** (cbox-ui 0.11) say whether an environment is
  `live` (real customers, real money; `false` shows the "Not production" banner), the
  `group` it is listed under in "All environments" ("Live", "Test", "Preview") and a
  `description`, a second line ("From branch feat/quotes"):

  ```php
  new ContextItem($env->id, $env->name, $env->slug,
      tone: Tone::Preview, live: false, group: 'Preview', description: 'From branch '.$env->branch);
  ```
- **A page whose URL omits this level** (the organization of `/projects/{project}`) still
  gets the level's pages in the rail, as long as `current()` resolves the item another way
  (from the project, say): links are filled from the resolved context.

## CreatesContextItems

Add it to a level to offer "New …". The menu asks only for a name.

```php
public function canCreate(Request $request, array $ancestors): bool
{
    return $request->user()->can('create', [Project::class, $ancestors[0]->id]);
}

public function create(Request $request, string $name, array $ancestors): CreatedContextItem
{
    $project = Project::create(['organization_id' => $ancestors[0]->id, 'name' => $name]);

    return new CreatedContextItem($project->public_id, route('projects.show', [...], false).'?setup=1');
}
```

`detailsUrl` is the page with the "Finish setting up {name}" section.

"New …" shows only when `canCreate()` is true, and the create endpoint answers 403
otherwise. Say no when the person cannot create here and now (no permission, a missing
API key). Never send them to another product to create it instead (spec §5.7).
`canCreate()` runs while the prop is built, so it must stay local.

## SearchProvider

The palette's "Records". `SearchQuery` holds the term, the current context (level key →
`ContextItem`) and the limit (5 by default).

```php
public function search(Request $request, SearchQuery $query): array
{
    return Deployment::query()
        ->whereEnvironment($query->context['environment']->id)
        ->whereLike('title', "%{$query->term}%")
        ->limit($query->limit)->get()
        ->map(fn ($d) => new SearchResult($d->public_id, $d->title, route('deployments.show', [...], false), $d->branch))
        ->all();
}
```

## NotificationSource

Without one, there is no bell. With one:

```php
Console::shell()->notifications(new ProductEvents);
```

`unread()` is read on every page, so keep it one indexed query. `latest()` and
`markRead()` back the bell's endpoints.

## HelpConfig

`resolve(Request): ShellHelp`. `StaticHelpConfig` covers the usual case:

```php
Console::shell()
    ->help(new StaticHelpConfig(sessionUrl: '/assistant/session', portalUrl: $portal, updatesUrl: '/assistant/updates'))
    ->helpTopic(fn (Request $r) => $r->route()?->getName());
```

Resolve it from config only. The browser calls the Assistant after first paint.

## ShellPreferenceStore

Bound to `CookieShellPreferences`. Bind your own to keep theme, rail pin, Help open and
the last context elsewhere. `lastContext()` must only ever return this person's own:

```php
$this->app->bind(ShellPreferenceStore::class, MyPreferences::class);
```

## Other hooks on the builder

| Method | What it changes |
|---|---|
| `->banner(fn (Request) => ?ShellBanner)` | An impersonation or notice strip; wins over the sandbox strip. |
| `->sandboxText(string\|fn (ContextItem) => string)` | The sandbox strip's text. |
| `->listFallback(fn (Route) => ?string)` | The list route an entity page falls back to on a switch. |
| `->appCatalogUrl(string\|fn (Request, array $context) => ?string)` | Where the app switcher reads the catalog, fixed or per request. |
| `->viewParameters(string ...)` | Route parameters that pick a view, not a record, on every route: a switch keeps the page. Per page: `->page($route, views: [...])`. |
| `->tabs(string ...)` | The phone's tab bar: up to four area keys. |
| `->creatables(list\|fn, default:)` | What "+" and the palette can create. |
| `->page($routes, create:, actions:, title:, views:)` | Describe a page (by name or pattern): its create target, its palette actions, its title, its view parameters. |
| `->recent(fn (Request, array $context) => list<PaletteRecord>)` | The palette's "Recent" before anything is typed. |
| `->helpArticles(fn (Request, ?string $topic) => list<ShellHelpArticle>)`, `->helpUnread(fn (Request) => ?int)` | Help's articles and its unread dot, from local data. |
| `->person(fn (Request) => ?ShellPerson)` | Who the account button shows. |
