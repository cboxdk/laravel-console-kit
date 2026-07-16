<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Branding\Branding;
use Cbox\Console\Kit\Branding\NullBrandingResolver;

/**
 * Resolves the branding the console shell should render for the current request —
 * the per-tenant palette, logo and app name a white-label plugin contributes. The
 * host renders it (`@consoleBrandingStyle`, `Console::branding()`); a plugin binds
 * an implementation that reads its own store. Unbound, it resolves to an EMPTY
 * {@see Branding} (see {@see NullBrandingResolver}), so
 * the shell keeps its static CSS with no plugin installed — deny-by-default: no
 * branding data means no injected styles.
 */
interface BrandingResolver
{
    public function resolve(): Branding;
}
