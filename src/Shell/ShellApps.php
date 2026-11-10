<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** `shell.apps`: the same-origin catalog the app switcher reads in the browser. */
readonly class ShellApps
{
    public function __construct(public string $catalogUrl = '/apps/catalog') {}

    /** @return array{catalogUrl: string} */
    public function toArray(): array
    {
        return ['catalogUrl' => $this->catalogUrl];
    }
}
