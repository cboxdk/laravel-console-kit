---
title: Requirements
description: The PHP and Laravel versions the kit installs on, and its one optional companion.
weight: 2
---

# Requirements

From `composer.json`:

| Requirement | Version |
|---|---|
| PHP | `^8.4` |
| `illuminate/contracts`, `illuminate/support`, `illuminate/view` | `^12.0 \|\| ^13.0` |

Optional:

| Package | Why |
|---|---|
| `inertiajs/inertia-laravel` (`^2.0 \|\| ^3.0`) | `console.shell` shares the `shell` prop lazily with every Inertia page. Blade views get it as `$shell` either way. |

The kit has no other runtime dependency. It does not depend on `cboxdk/laravel-id-client`;
that package plugs its `OrganizationLevel` into the kit when both are installed.
