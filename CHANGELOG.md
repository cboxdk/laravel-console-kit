# Changelog

## v0.2.6 — 2026-11-11

### Added
- The cbox-ui 0.11 shell (V6 phone layout, V7 create sheet). All optional; an app that declares none of it sends the same prop as before.
  - Environments: `ContextItem` takes `live`, `group` and `description`; `Tone` adds `Staging`, `Preview` and `Development`.
  - `->tabs('overview', 'invoices', …)`: the phone's tab bar (`nav.tabs`, up to four area keys; an area not shown on a page is left out of the bar there).
  - `->creatables([new ShellCreatable($key, $label, $area)], default: 'invoice')`: `shell.create`, a list or a closure over the request and the context. A default that is not a creatable is not sent.
  - `->page($routes, create:, actions:, title:, views:)`: describes a page by route name or `Str::is()` pattern and sends `shell.page` (`title`, `route`, `create`, `actions`). `page.create` is sent only when it is one of the request's creatables. `page` is also sent, with the nav page's title, while the shell can create.
  - `PaletteAction` takes `hint` and `create` (`route(..., hint:, create:)`, `url(..., hint:, create:)`, `->withHint()`, `->withCreate()`).
  - `->recent(fn (Request, array $context) => list<PaletteRecord>)`: `palette.recent`, same-origin URLs only.
  - `->helpArticles(fn (Request, ?string $topic) => list<ShellHelpArticle>)` and `->helpUnread(fn (Request) => ?int)`: `help.articles` and `help.unread`.
- `->appCatalogUrl()` also takes a closure over the request and the context, so the switcher can ask for the page's organization. Null, or anything but a same-origin path or an `https://` URL, keeps the default.
- View parameters: `->page($route, views: ['signal'])` or `->viewParameters('signal')` marks a required route parameter as a view of the page, not a record. A switch keeps the page and the view instead of falling back to the list.
- `PaletteAction::resolveIn($router, $current, $context)`: `resolve()` with the context's route parameters as a fallback.
- `FakeContextLevel::currentUsing(fn)` resolves the current item another way than from the route parameter. `AssertableShell::assertTabs()`, `assertCanCreateItem()`, `assertPageCreates()` and `assertPageAction()`.

### Fixed
- A page whose URL omits an outer level (`/projects/{project}/…`, the organization resolved from the project) got an empty rail: nav links were built only from the current route's parameters. Nav pages, palette actions, locked-page upgrade links and the account links now fill a missing parameter from the resolved context (each level's `routeParameter()` => the current item's `routeKey`). The page's own parameters still win.
- A switch from cbox-ui, which posts the outer levels' current ids along with the new one (`select[organization]=<current>&select[project]=<new>`), was treated as an organization switch. On a page without the organization in its URL that landed on the project's home instead of the same page. The switched level is now the outermost whose id changed.

Backward compatible with 0.2.x.

## v0.2.5 — 2026-11-11

### Fixed
- Switching organization, project or environment from cbox-ui 0.10.3 and older did nothing: cbox-ui posted `{<level>: id, return_to}` and `/shell/switch` read only `select[]` and `from`, so it answered 404 "Nothing to switch to.". The endpoint now also reads that flat body when the request carries no `select` (only keys that name a declared level; `return_to` stands in for `from`, with the same same-origin check). `select`/`from` stays the contract and wins when both are sent; cbox-ui 0.10.4 sends it.

### Deprecated
- The flat switch body. It is accepted through 0.2.x and removed in 0.3.

Backward compatible with 0.2.x.

## v0.2.4 — 2026-11-11

### Added
- `shell.banners`: every strip under the header, the sandbox strip first, then the app's banners. `->banner(fn)` may return a list, and `->addBanner(fn)` adds another resolver (a plugin's). An app banner (a tax hold) no longer hides the sandbox strip. `shell.banner` keeps the first app banner, else the strip, for cbox-ui 0.10.1 and older. `AssertableShell::assertBanners([...])` and `assertHasBanner($kind, $text)`; `assertNoBanner()` also checks `banners`.
- `->account(signedInWith: fn (Request $request) => …)`: the "Signed in with" line can be read per request (Cbox ID's environment name).
- `InteractsWithShell::freshShellRequest()`: forgets the scoped instances between two requests of one HTTP test, as a new request does in production.

### Fixed
- A record page lit a sibling nav page that only shared its route-name prefix: `billing.subscriptions.show` lit `billing.subscriptions.dunning`. The order is now the app's `->listFallback()`, the nearest parent route that is a nav page, then the nav page sharing the most whole name segments (the `.index` page first on a tie). The switch's list fallback uses the same rule after the resource's `.index` route.

All additions are optional and backward compatible with 0.2.x.

## v0.2.3 — 2026-11-11

### Added
- Locked nav pages: `->page(..., feature: 'coupons', lockedWhenOff: true, upgradeUrl: 'billing.upgrade')` shows a page whose feature is off with `locked: true`, linking to the upgrade route or path (else to the page itself), instead of leaving it out.
- Nav page badges: `->page(..., count: fn (Request $request, array $context) => 3)`, sent as `count` when above 0.
- Nav page groups: `->page(..., group: 'Organization')`, sent as `group`; pages of one group are sent together.
- `->navFilter(fn (NavPage $page, Request $request, array $context): bool)` on the shell builder: leave out nav pages per request (a page that would answer 403).
- `->palettePlaceholder(string|Closure)` on the shell builder, sent as `palette.placeholder`.
- `PaletteAction::route(..., fragment: 'invite')` adds `#invite` to the action's URL.
- `shell.account` for the account menu: `->account(profileUrl:, switchAccountUrl:, signOutUrl:, signOutMethod:, signedInWith:, languages:, formats:)`, each URL a route name, a path, an `https://` URL or a closure. Defaults: Cbox ID's profile page through laravel-id-client (else the `profile` route), `login.switch` and `logout`. Left out of the prop when nothing resolves.
- Nav areas, nav pages and the banner action also send their link as `url`, the name cbox-ui's types read. `href` stays.

### Fixed
- A record page whose list is not named `.index` (`billing.coupons.show` → `billing.coupons`) now lights its list page and area, so the area's subnav shows. The app's `->listFallback()` is used first, then the resource's pages, then the nearest parent route that is a nav page.

All additions are optional and backward compatible with 0.2.x.

## v0.2.2 — 2026-11-10

### Added
- `Console::shell()`: builds the `shell` prop that `@cboxdk/cbox-ui` 0.10's `CboxShell` renders (Cbox shell v1). The prop covers the rail navigation, the context levels, palette search and actions, Help, notifications, the app catalog, a banner and preferences.
- Contracts an app implements:
  - `ContextLevel`: the current item, a paginated searchable list, optional create, all and settings URLs;
  - `SearchProvider`;
  - `PaletteAction`;
  - `NotificationSource`;
  - `HelpConfig`.
- `HandleShell` middleware. It shares `shell` with Inertia pages, or with Blade views when Inertia is not installed.
- `Console::shellRoutes()` adds the list, create, switch, search, go-to and preferences endpoints. The switch endpoint stays on the same page, or falls back to the area list, and never carries an entity id across contexts.
- `Console::shellHome()` sends `/` to the person's last context.
- Per-person preferences (rail pinned, Help open), read before first paint.
- Nav pages and areas can be gated on an ability: `can:` on `page()`, and `->can()` on areas.
- Testing fakes: `FakeContextLevel`, `FakeSearchProvider` and a shell assertion helper.

All additions are optional and backward compatible with 0.2.x.
