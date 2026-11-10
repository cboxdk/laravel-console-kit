<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Nav;

use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Cbox\Console\Kit\Shell\ContextItem;
use Closure;
use Illuminate\Http\Request;

/**
 * A single page (a leaf) inside a nav area.
 *
 *  - `feature`, when set, gates the page: it shows only when that feature is
 *    {@see FeatureRegistry::active()}. With `lockedWhenOff`, a page whose feature is off
 *    shows as locked instead of being left out, and links to `upgradeUrl` (a route name
 *    or a `/path`), else to its own route.
 *  - `ability`, when set, gates the page on the person's permission: it shows only when
 *    `Gate::forUser($request->user())->allows($ability, $arguments)`. The arguments are a
 *    list, or a closure that builds them from the request and the context the page is in
 *    (outermost first), so a policy can check the current organization or project. A page
 *    the person may not open is left out of the rail and the palette, never shown to land
 *    on a 403. It is never shown locked either.
 *  - `count` is an optional badge, such as open drafts: an int, or a closure over the
 *    request and the context, evaluated for every page render (keep it cheap: a cached or
 *    precomputed number). Null or 0 shows no badge.
 *  - `group` is an optional heading inside the area's subnav (Settings → "Organization",
 *    "Project"). Pages of one group are shown together, in the order the first of each
 *    group appears.
 */
class NavPage
{
    /**
     * @param  list<mixed>|(Closure(Request, list<ContextItem>): list<mixed>)  $abilityArguments
     * @param  int|(Closure(Request, list<ContextItem>): ?int)|null  $count
     */
    public function __construct(
        public readonly string $route,
        public readonly string $label,
        public readonly ?string $feature = null,
        public readonly int $order = 100,
        public readonly ?string $ability = null,
        public readonly array|Closure $abilityArguments = [],
        public readonly bool $lockedWhenOff = false,
        public readonly ?string $upgradeUrl = null,
        public readonly int|Closure|null $count = null,
        public readonly ?string $group = null,
    ) {}
}
