<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Middleware;

use Cbox\Console\Kit\Shell\ShellBuilder;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the `shell` prop. Aliased `console.shell`; put it on the app's page routes.
 *
 * The prop is built lazily, at most once per request, and only when something renders it:
 *
 *  - With Inertia installed it is an Inertia shared prop, so a redirect, a JSON answer or a
 *    partial reload that leaves it out never builds it.
 *  - Any Blade view rendered for the request also gets it as `$shell` (an array), through
 *    the view composer the service provider registers. Nothing is built for a request that
 *    renders no view.
 */
class HandleShell
{
    /** The request attribute holding the memoised builder the view composer reads. */
    public const ATTRIBUTE = 'console.shell';

    public function __construct(private readonly ShellBuilder $shell) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $built = null;
        $build = function () use ($request, &$built): array {
            return $built ??= $this->shell->build($request)->toArray();
        };

        $request->attributes->set(self::ATTRIBUTE, $build);

        if (class_exists(Inertia::class)) {
            Inertia::share('shell', $build);
        }

        return $next($request);
    }
}
