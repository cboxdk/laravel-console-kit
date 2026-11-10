<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Nav;

use Cbox\Console\Kit\Shell\ContextItem;
use Closure;
use Illuminate\Http\Request;

/**
 * A top-level nav area (a group) holding pages. A host seeds its default areas; a
 * plugin fetches an area by key (creating it if new, or adding to a host area like
 * "overview") and appends pages — no host edit required.
 *
 * An area and each page can be gated on a feature and on an ability (see {@see NavPage}).
 * An area whose own ability is denied is left out with all its pages.
 */
class NavArea
{
    /** @var list<NavPage> */
    private array $pages = [];

    /**
     * @param  list<mixed>|(Closure(Request, list<ContextItem>): list<mixed>)  $abilityArguments
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $icon = null,
        public int $order = 100,
        public ?string $feature = null,
        public ?string $ability = null,
        public array|Closure $abilityArguments = [],
    ) {}

    /**
     * Append a page. Fluent, so a plugin can chain several.
     *
     * `can` gates it on an ability (`->page('billing.plan', 'Plan', can: 'manage-billing')`),
     * checked with `$arguments` (a list, or a closure over the request and the context).
     *
     * `lockedWhenOff: true` shows the page locked while its feature is off, linking to
     * `upgradeUrl` (a route name or a `/path`), instead of leaving it out. `count` is a
     * badge (an int, or a closure over the request and the context). `group` is a heading
     * inside the area's subnav. See {@see NavPage}.
     *
     * @param  list<mixed>|(Closure(Request, list<ContextItem>): list<mixed>)  $arguments
     * @param  int|(Closure(Request, list<ContextItem>): ?int)|null  $count
     */
    public function page(
        string $route,
        string $label,
        ?string $feature = null,
        int $order = 100,
        ?string $can = null,
        array|Closure $arguments = [],
        bool $lockedWhenOff = false,
        ?string $upgradeUrl = null,
        int|Closure|null $count = null,
        ?string $group = null,
    ): self {
        $this->pages[] = new NavPage($route, $label, $feature, $order, $can, $arguments, $lockedWhenOff, $upgradeUrl, $count, $group);

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Gate the whole area on an ability.
     *
     * @param  list<mixed>|(Closure(Request, list<ContextItem>): list<mixed>)  $arguments
     */
    public function can(?string $ability, array|Closure $arguments = []): self
    {
        $this->ability = $ability;
        $this->abilityArguments = $arguments;

        return $this;
    }

    /** Gate the whole area on a feature. */
    public function feature(?string $feature): self
    {
        $this->feature = $feature;

        return $this;
    }

    /**
     * The area's pages, ordered.
     *
     * @return list<NavPage>
     */
    public function pages(): array
    {
        $pages = $this->pages;
        usort($pages, static fn (NavPage $a, NavPage $b): int => $a->order <=> $b->order);

        return $pages;
    }
}
