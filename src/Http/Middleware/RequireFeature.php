<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Middleware;

use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: 404s unless the named feature is active. Aliased `console.feature`, so a
 * plugin's routes (`->middleware('console.feature:billing')`) are unreachable when the
 * plugin's feature is off — the route simply does not exist for a self-hosted install.
 */
final class RequireFeature
{
    public function __construct(private readonly FeatureRegistry $features) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->features->active($feature), 404);

        return $next($request);
    }
}
