<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Middleware;

use Cbox\Console\Kit\Shell\ShellBuilder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the `shell` prop. Aliased `console.shell`; put it on the app's page routes.
 *
 * With Inertia installed the prop is shared lazily: it is built only when a page renders,
 * never for a redirect or a JSON answer, and a partial reload that leaves it out does not
 * build it. Without Inertia it is shared with every Blade view as `$shell` (an array).
 */
class HandleShell
{
    public function __construct(private readonly ShellBuilder $shell) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $build = fn (): array => $this->shell->build($request)->toArray();

        if (class_exists(Inertia::class)) {
            Inertia::share('shell', $build);
        } else {
            View::share('shell', $build());
        }

        return $next($request);
    }
}
