---
title: Adopt the shared shell
description: Move an app onto the shared Cbox shell — describe it, declare its levels, share it, render it, and test it.
weight: 2
---

# Adopt the shared shell

A checklist for moving an app onto the one shell.

1. **URLs.** Put the organization in the path, `/orgs/{org}/…`, with the slug. Levels 2
   and 3 are path segments too: `/orgs/{org}/projects/{project}/environments/{environment}/…`.
2. **Levels.** Use `Cbox\Id\Client\Shell\OrganizationLevel` from laravel-id-client for the
   organization. Write the app's own levels against its tables
   ([how](../extension-points/context-levels.md)).
3. **Nav.** Seed `Console::nav()` with the app's areas. Name list routes `<resource>.index`
   and record routes `<resource>.show`, so an entity page falls back to its list on a
   switch.
4. **Describe the shell** in a service provider: `->app()`, `->levels()`, `->search()`,
   `->actions()`, `->help()`, `->helpTopic()`, `->notifications()` when the app has
   product events.
5. **Routes.** `Console::shellRoutes()` in `routes/web.php`; `console.shell` on the page
   routes; laravel-id-client's `cbox-id.org-url` on the routes under `/orgs/{org}`.
   A page that cannot carry every level in its URL still gets a full rail as long as the
   level's `current()` resolves the item another way; prefer the URL standard anyway.
   A required parameter that picks a view, not a record (`{signal}`), is declared with
   `->page($route, views: ['signal'])` so a switch keeps the page.
6. **Phone and create** (cbox-ui 0.11): `->tabs()` for the bottom tab bar, `->creatables()`
   for "+", `->page($route, create:, actions:)` for each list page, and `live`, `group`
   and `description` on the environments ([pages and create](../core-concepts/shell.md#pages-and-create)).
7. **Render** cbox-ui's `CboxShell` from the persistent layout with `props.shell`.
8. **Delete** the app's copied `shared-shell.css`, `app-switcher.ts`, `Shell.tsx`,
   `service-marks.json` and `chrome/*`.
9. **Test** with `InteractsWithShell`: the levels on a deep page, the active area, the
   switch from an entity page, and "New …".
