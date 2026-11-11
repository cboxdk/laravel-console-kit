<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.nav`: the rail's areas, which area and page the request is on, and the phone's
 * bottom tab bar (`tabs`: up to four area keys, cbox-ui 0.11; absent, the first four).
 */
readonly class ShellNav
{
    /**
     * @param  list<ShellNavArea>  $areas
     * @param  list<string>  $tabs
     */
    public function __construct(
        public array $areas = [],
        public string $activeArea = '',
        public ?string $activePage = null,
        public array $tabs = [],
    ) {}

    /** @return array{areas: list<array<string, mixed>>, activeArea: string, activePage?: string, tabs?: list<string>} */
    public function toArray(): array
    {
        $out = [
            'areas' => array_map(static fn (ShellNavArea $area): array => $area->toArray(), $this->areas),
            'activeArea' => $this->activeArea,
        ];

        if ($this->activePage !== null) {
            $out['activePage'] = $this->activePage;
        }

        if ($this->tabs !== []) {
            $out['tabs'] = $this->tabs;
        }

        return $out;
    }
}
