<?php

declare(strict_types=1);

namespace Cbox\Console\Kit;

use Cbox\Console\Kit\Branding\Branding;
use Cbox\Console\Kit\Contracts\BrandingResolver;
use Cbox\Console\Kit\Contracts\CurrentContext;
use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Cbox\Console\Kit\Contracts\NavRegistry;
use Cbox\Console\Kit\Contracts\SlotRegistry;
use Closure;

/**
 * The one entry point a plugin registers into (`Console::nav()`, `Console::features()`,
 * `Console::slots()`, `Console::dashboardCard()`) and the host reads from when it
 * renders its shell. A thin facade over the registries.
 */
final class ConsoleManager
{
    /** The well-known slot the host renders for contributed dashboard cards. */
    public const DASHBOARD_CARDS = 'console.dashboard.cards';

    public function __construct(
        private readonly NavRegistry $nav,
        private readonly FeatureRegistry $features,
        private readonly SlotRegistry $slots,
        private readonly CurrentContext $context,
        private readonly BrandingResolver $branding,
    ) {}

    public function nav(): NavRegistry
    {
        return $this->nav;
    }

    /** Who the console request is for (current org / user / admin). */
    public function context(): CurrentContext
    {
        return $this->context;
    }

    /**
     * The branding the shell should render for the current request — a per-tenant
     * palette/logo/app-name a white-label plugin contributes, or an EMPTY
     * {@see Branding} (static CSS) when none is bound. Resolved fresh each call so it
     * tracks the current tenant. Echoed via `@consoleBrandingStyle`.
     */
    public function branding(): Branding
    {
        return $this->branding->resolve();
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
