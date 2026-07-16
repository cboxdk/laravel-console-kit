<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Nav;

/**
 * A top-level nav area (a group) holding pages. A host seeds its default areas; a
 * plugin fetches an area by key (creating it if new, or adding to a host area like
 * "overview") and appends pages — no host edit required.
 */
final class NavArea
{
    /** @var list<NavPage> */
    private array $pages = [];

    public function __construct(
        public string $key,
        public string $label,
        public ?string $icon = null,
        public int $order = 100,
        public ?string $feature = null,
    ) {}

    /** Append a page. Fluent, so a plugin can chain several. */
    public function page(string $route, string $label, ?string $feature = null, int $order = 100): self
    {
        $this->pages[] = new NavPage($route, $label, $feature, $order);

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
