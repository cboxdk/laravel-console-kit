<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Illuminate\Http\Request;

/**
 * Where `/` lands (spec §5.6): the context this person last switched to, if they can
 * still open it; else their first top-level item's own page (`/orgs/cbox`); else the
 * top level's collection (`/orgs`), where they can create one.
 *
 * Local only: the last context comes from the {@see ShellPreferenceStore}, and "can still
 * open it" means the top level's `current()` still resolves the stored path.
 */
class ShellHome
{
    public function __construct(
        private readonly ShellBuilder $shell,
        private readonly ContextSwitcher $switcher,
    ) {}

    /** The URL to send the person to, or null when there is nowhere better than here. */
    public function url(Request $request): ?string
    {
        $here = '/'.ltrim($request->path(), '/');
        $last = $this->shell->preferenceStore()->lastContext($request);

        if ($last !== null && parse_url($last, PHP_URL_PATH) !== $here && $this->stillOpen($request, $last)) {
            return $last;
        }

        $top = $this->shell->contextLevels()[0] ?? null;

        if ($top === null) {
            return null;
        }

        $first = $top->list($request, new ContextQuery(null, [], null, 1))->items[0] ?? null;
        $url = $first instanceof ContextItem ? $top->url($first, []) : $top->allUrl([]);

        return parse_url($url, PHP_URL_PATH) === $here ? null : $url;
    }

    private function stillOpen(Request $request, string $path): bool
    {
        $target = $this->switcher->requestFor($request, $path);

        if (! $target instanceof Request) {
            return false;
        }

        return $this->shell->contextLevels() === [] || $this->shell->resolveContext($target)->items !== [];
    }
}
