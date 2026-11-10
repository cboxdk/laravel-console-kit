<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** One page of a nav area on the wire: its route name as `key`, the label and the resolved URL. */
readonly class ShellNavPage
{
    public function __construct(
        public string $key,
        public string $label,
        public string $href,
    ) {}

    /** @return array{key: string, label: string, href: string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'href' => $this->href];
    }
}
