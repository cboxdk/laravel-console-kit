<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.page`: the page the request is on, for the palette's "On {page}" group, the
 * phone's "+" and the chat's page intent (cbox-ui 0.11). `create` is the key of the
 * creatable this page makes; `actions` are what can be done here, shown first in the palette.
 */
readonly class ShellPage
{
    /**
     * @param  list<PaletteAction>  $actions
     */
    public function __construct(
        public string $title = '',
        public ?string $route = null,
        public ?string $create = null,
        public array $actions = [],
    ) {}

    /** @return array{title: string, route?: string, create?: string, actions?: list<array<string, mixed>>} */
    public function toArray(): array
    {
        $out = ['title' => $this->title];

        if ($this->route !== null && $this->route !== '') {
            $out['route'] = $this->route;
        }

        if ($this->create !== null && $this->create !== '') {
            $out['create'] = $this->create;
        }

        if ($this->actions !== []) {
            $out['actions'] = array_map(static fn (PaletteAction $action): array => $action->toArray(), $this->actions);
        }

        return $out;
    }
}
