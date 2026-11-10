<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * One page of a nav area on the wire: its route name as `key`, the label and the resolved
 * URL, sent as `href` and as `url` (the name cbox-ui's `NavPage` reads).
 *
 * Optional, and left out unless set: `locked` (the page's feature is off; it links to the
 * upgrade prompt), `count` (a badge, only when above 0) and `group` (a subnav heading).
 */
readonly class ShellNavPage
{
    public function __construct(
        public string $key,
        public string $label,
        public string $href,
        public bool $locked = false,
        public ?int $count = null,
        public ?string $group = null,
    ) {}

    /** @return array{key: string, label: string, href: string, url: string, locked?: true, count?: int, group?: string} */
    public function toArray(): array
    {
        $out = ['key' => $this->key, 'label' => $this->label, 'href' => $this->href, 'url' => $this->href];

        if ($this->locked) {
            $out['locked'] = true;
        }

        if ($this->count !== null && $this->count > 0) {
            $out['count'] = $this->count;
        }

        if ($this->group !== null && $this->group !== '') {
            $out['group'] = $this->group;
        }

        return $out;
    }
}
