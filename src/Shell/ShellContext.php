<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** `shell.context`: the 0–3 resolved levels, in order, and where a switch is posted. */
readonly class ShellContext
{
    /**
     * @param  list<ShellContextLevel>  $levels
     */
    public function __construct(
        public array $levels = [],
        public string $switchUrl = '',
    ) {}

    /** @return array{levels: list<array<string, mixed>>, switchUrl: string} */
    public function toArray(): array
    {
        return [
            'levels' => array_map(static fn (ShellContextLevel $level): array => $level->toArray(), $this->levels),
            'switchUrl' => $this->switchUrl,
        ];
    }
}
