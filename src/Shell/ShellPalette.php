<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.palette`: where ⌘K asks for records and "Go to", the app's actions, and the
 * search field's placeholder when the app sets one (cbox-ui's default is "Search or ask…"),
 * and the records it suggests under "Recent" before anything is typed (cbox-ui 0.11).
 */
readonly class ShellPalette
{
    /**
     * @param  list<PaletteAction>  $actions
     * @param  list<PaletteRecord>  $recent
     */
    public function __construct(
        public string $searchUrl = '',
        public array $actions = [],
        public ?string $placeholder = null,
        public array $recent = [],
    ) {}

    /** @return array{searchUrl: string, actions: list<array<string, mixed>>, placeholder?: string, recent?: list<array<string, mixed>>} */
    public function toArray(): array
    {
        $out = [
            'searchUrl' => $this->searchUrl,
            'actions' => array_map(static fn (PaletteAction $action): array => $action->toArray(), $this->actions),
        ];

        if ($this->placeholder !== null && $this->placeholder !== '') {
            $out['placeholder'] = $this->placeholder;
        }

        if ($this->recent !== []) {
            $out['recent'] = array_map(static fn (PaletteRecord $record): array => $record->toArray(), $this->recent);
        }

        return $out;
    }
}
