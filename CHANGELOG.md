# Changelog

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
