<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Closure;

/**
 * Which optional features are present and enabled. **Deny-by-default**: a feature that
 * was never registered is inactive. A plugin registers its feature (often with a
 * resolver that also checks config or an entitlement); the host registers its own
 * (e.g. `sso` gated on an entitlement). Nav pages, routes and UI slots all key off
 * {@see active()}, so "everything is gated on the plugin" is one uniform check.
 */
interface FeatureRegistry
{
    /**
     * Register a feature. `$enabled` may be a bool or a resolver evaluated each check
     * (so it can consult config, a bound service, or an entitlement live).
     *
     * @param  bool|Closure(): bool  $enabled
     */
    public function register(string $key, bool|Closure $enabled = true): void;

    /** True only if the feature was registered AND is currently enabled. */
    public function active(string $key): bool;

    /**
     * The keys of every currently-active feature.
     *
     * @return list<string>
     */
    public function activeKeys(): array;
}
