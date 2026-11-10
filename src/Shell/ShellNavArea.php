<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * A rail area on the wire. `href` is the area's first visible page; `pages` are its
 * visible pages (the subnav shows only when there is more than one). `icon` is a name
 * the client maps to an icon component, never markup.
 */
readonly class ShellNavArea
{
    /**
     * @param  list<ShellNavPage>  $pages
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $href,
        public array $pages = [],
        public ?string $icon = null,
    ) {}

    /** @return array{key: string, label: string, href: string, icon?: string, pages: list<array{key: string, label: string, href: string}>} */
    public function toArray(): array
    {
        $out = ['key' => $this->key, 'label' => $this->label, 'href' => $this->href];

        if ($this->icon !== null) {
            $out['icon'] = $this->icon;
        }

        $out['pages'] = array_map(static fn (ShellNavPage $page): array => $page->toArray(), $this->pages);

        return $out;
    }
}
