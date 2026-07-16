<?php

declare(strict_types=1);

namespace Cbox\Console\Kit;

use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Cbox\Console\Kit\Contracts\NavRegistry;
use Cbox\Console\Kit\Contracts\SlotRegistry;
use Closure;

/**
 * The one entry point a plugin registers into (`Console::nav()`, `Console::features()`,
 * `Console::slots()`, `Console::dashboardCard()`) and the host reads from when it
 * renders its shell. A thin facade over the three registries.
 */
final class ConsoleManager
{
    /** The well-known slot the host renders for contributed dashboard cards. */
    public const DASHBOARD_CARDS = 'console.dashboard.cards';

    public function __construct(
        private readonly NavRegistry $nav,
        private readonly FeatureRegistry $features,
        private readonly SlotRegistry $slots,
    ) {}

    public function nav(): NavRegistry
    {
        return $this->nav;
    }

    public function features(): FeatureRegistry
    {
        return $this->features;
    }

    public function slots(): SlotRegistry
    {
        return $this->slots;
    }

    /** Convenience: is a feature present and enabled? */
    public function featureActive(string $key): bool
    {
        return $this->features->active($key);
    }

    /**
     * Contribute a card to the overview dashboard — sugar over the well-known slot.
     *
     * @param  string|Closure(array<string, mixed>): mixed  $content
     */
    public function dashboardCard(string|Closure $content, int $order = 100): void
    {
        $this->slots->add(self::DASHBOARD_CARDS, $content, $order);
    }
}
