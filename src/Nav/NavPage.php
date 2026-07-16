<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Nav;

/**
 * A single page (a leaf) inside a nav area. `feature`, when set, gates the page: it
 * shows only when that feature is {@see \Cbox\Console\Kit\Contracts\FeatureRegistry::active()}.
 */
final class NavPage
{
    public function __construct(
        public readonly string $route,
        public readonly string $label,
        public readonly ?string $feature = null,
        public readonly int $order = 100,
    ) {}
}
