<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** `shell.palette`: where ⌘K asks for records and "Go to", and the app's actions. */
readonly class ShellPalette
{
    /**
     * @param  list<PaletteAction>  $actions
     */
    public function __construct(
        public string $searchUrl = '',
        public array $actions = [],
    ) {}

    /** @return array{searchUrl: string, actions: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'searchUrl' => $this->searchUrl,
            'actions' => array_map(static fn (PaletteAction $action): array => $action->toArray(), $this->actions),
        ];
    }
}
