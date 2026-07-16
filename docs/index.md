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

- **[README](../README.md)** — the four hooks and the host wiring at a glance.
- **[Cookbook](cookbook/_index.md)** — task recipes, including copy-paste prompts for
  adopting the socket in a host and building a plug.
