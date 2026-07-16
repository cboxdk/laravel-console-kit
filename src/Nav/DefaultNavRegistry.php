<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Nav;

use Cbox\Console\Kit\Contracts\NavRegistry;

final class DefaultNavRegistry implements NavRegistry
{
    /** @var array<string, NavArea> */
    private array $areas = [];

    public function area(string $key, ?string $label = null, ?string $icon = null, int $order = 100): NavArea
    {
        if (! isset($this->areas[$key])) {
            $this->areas[$key] = new NavArea($key, $label ?? ucfirst($key), $icon, $order);

            return $this->areas[$key];
        }

        // Existing area (e.g. a host default): apply any explicitly-passed overrides.
        $area = $this->areas[$key];
        if ($label !== null) {
            $area->label($label);
        }
        if ($icon !== null) {
            $area->icon($icon);
        }

        return $area;
    }

    public function areas(): array
    {
        $areas = array_values($this->areas);
        usort($areas, static fn (NavArea $a, NavArea $b): int => $a->order <=> $b->order);

        return $areas;
    }
}
