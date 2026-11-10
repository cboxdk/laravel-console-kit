---
title: Cbox Console Kit
description: The extension socket a Cbox admin console exposes so an optional package can composer-require in and light up a whole feature — nav, UI and gates — with zero host edits.
weight: 0
---

# Cbox Console Kit

`cboxdk/laravel-console-kit` is the plug **socket**: the four hooks an admin console
exposes — **nav**, **features**, **slots**, **dashboard cards** — plus a `CurrentContext`
so a plugin can resolve the current org/user host-agnostically. A feature package
(billing, and others) is the **plug**: it `composer require`s in and registers into the
hooks, lighting up nav + UI + gates with **zero edits to the host**. The same kit is
adopted by every console (cbox-id, ai-assistant, cortex, …), so one plugin works across
all of them.

Deny-by-default throughout: an unregistered feature is off, a gated page is hidden, a
guarded route 404s. The kit holds no feature logic and no UI of its own — just the
shared socket.

It also builds the **shared shell**: the `shell` prop every Cbox app renders with
cbox-ui (rail, context selectors, ⌘K palette, Help, bell, app switcher), the endpoints
behind its menus, and the contracts an app implements to fill it.

- **[Quickstart](quickstart.md)** — the shared shell in four steps.
- **[Requirements](requirements.md)** — PHP, Laravel and the optional Inertia companion.
- **[README](../README.md)** — the four hooks and the host wiring at a glance.
- **[Getting started](getting-started/_index.md)** — install, and [test](getting-started/testing.md) with the fakes.
- **[Core concepts](core-concepts/_index.md)** — [the shared shell](core-concepts/shell.md): the prop, switching, local first.
- **[Extension points](extension-points/_index.md)** — [context levels and providers](extension-points/context-levels.md).
- **[Cookbook](cookbook/_index.md)** — task recipes, including copy-paste prompts for
  adopting the socket in a host and building a plug.
