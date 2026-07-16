<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Branding;

use Cbox\Console\Kit\Contracts\BrandingResolver;

/**
 * The default {@see BrandingResolver} when a host or plugin binds none: it resolves
 * an EMPTY {@see Branding}, so the console shell renders on its static CSS and
 * `@consoleBrandingStyle` injects nothing. Bound via `bindIf` in the service
 * provider, so a white-label plugin can override it without touching the host.
 */
final class NullBrandingResolver implements BrandingResolver
{
    public function resolve(): Branding
    {
        return Branding::empty();
    }
}
