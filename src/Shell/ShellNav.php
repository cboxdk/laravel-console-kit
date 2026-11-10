<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** `shell.nav`: the rail's areas and which area and page the request is on. */
readonly class ShellNav
{
    /**
     * @param  list<ShellNavArea>  $areas
     */
    public function __construct(
        public array $areas = [],
        public string $activeArea = '',
        public ?string $activePage = null,
    ) {}

    /** @return array{areas: list<array<string, mixed>>, activeArea: string, activePage?: string} */
    public function toArray(): array
    {
        $out = [
            'areas' => array_map(static fn (ShellNavArea $area): array => $area->toArray(), $this->areas),
            'activeArea' => $this->activeArea,
        ];

        if ($this->activePage !== null) {
            $out['activePage'] = $this->activePage;
        }

        return $out;
    }
}
