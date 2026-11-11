<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What the app declares about one page, or a family of pages, through
 * `Console::shell()->page(...)`. It stays on the server: {@see ShellBuilder} turns it into
 * `shell.page`, and the context switch reads `views`.
 *
 * `routes` are route names or `Str::is()` patterns (`projects.signals.*`). An exact name
 * wins over a pattern; among patterns, the first declared wins.
 */
readonly class ShellPageDefinition
{
    /**
     * @param  list<string>  $routes
     * @param  list<PaletteAction>|(Closure(Request): list<PaletteAction>)  $actions
     * @param  string|(Closure(Request): ?string)|null  $title
     * @param  list<string>  $views  required route parameters that pick a view of the page, not a record
     */
    public function __construct(
        public array $routes = [],
        public ?string $create = null,
        public array|Closure $actions = [],
        public string|Closure|null $title = null,
        public array $views = [],
    ) {}

    /** Whether route `$name` is one of {@see $routes} by name. */
    public function names(string $name): bool
    {
        return in_array($name, $this->routes, true);
    }

    /** Whether route `$name` matches one of {@see $routes} as a pattern. */
    public function matches(string $name): bool
    {
        foreach ($this->routes as $pattern) {
            if (str_contains($pattern, '*') && Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
