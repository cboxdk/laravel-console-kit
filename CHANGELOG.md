# Changelog

## Unreleased (suggested v0.2.5)

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
