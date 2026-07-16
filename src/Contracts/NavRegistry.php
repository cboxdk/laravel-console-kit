<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Nav\NavArea;

/**
 * The console navigation, as data a plugin can extend. The host seeds its default
 * areas/pages, a plugin adds its own — both call {@see area()}. The host shell reads
 * {@see areas()} and renders it (filtering gated pages via the feature registry).
 */
interface NavRegistry
{
    /**
     * Get an area by key, creating it if new. Passing a label/icon/order sets them
     * (a plugin can therefore create "Billing", or add pages to the host's "overview").
     */
    public function area(string $key, ?string $label = null, ?string $icon = null, int $order = 100): NavArea;

    /**
     * All areas, ordered.
     *
     * @return list<NavArea>
     */
    public function areas(): array;
}
