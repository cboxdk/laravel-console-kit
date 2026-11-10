<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.palette`: where ⌘K asks for records and "Go to", the app's actions, and the
 * search field's placeholder when the app sets one (cbox-ui's default is "Search or ask…").
 */
readonly class ShellPalette
{
    /**
     * @param  list<PaletteAction>  $actions
     */
    public function __construct(
        public string $searchUrl = '',
        public array $actions = [],
        public ?string $placeholder = null,
    ) {}

    /** @return array{searchUrl: string, actions: list<array<string, mixed>>, placeholder?: string} */
    public function toArray(): array
    {
        $out = [
            'searchUrl' => $this->searchUrl,
            'actions' => array_map(static fn (PaletteAction $action): array => $action->toArray(), $this->actions),
        ];

        if ($this->placeholder !== null && $this->placeholder !== '') {
            $out['placeholder'] = $this->placeholder;
        }

        return $out;
    }
}
