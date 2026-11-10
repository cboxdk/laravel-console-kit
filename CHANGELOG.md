# Changelog

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
