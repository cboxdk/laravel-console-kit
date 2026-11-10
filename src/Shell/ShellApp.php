<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** `shell.app`: which Cbox app this is (its product key), its name and where its brand row links. */
readonly class ShellApp
{
    public function __construct(
        public string $key = 'app',
        public string $name = 'App',
        public string $homeUrl = '/',
    ) {}

    /** @return array{key: string, name: string, homeUrl: string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'name' => $this->name, 'homeUrl' => $this->homeUrl];
    }
}
