<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** A labelled link, as the banner's optional action. */
readonly class ShellLink
{
    public function __construct(
        public string $label,
        public string $href,
    ) {}

    /** @return array{label: string, href: string} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'href' => $this->href];
    }
}
