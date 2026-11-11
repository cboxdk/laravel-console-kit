<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\URL;

/**
 * Builds same-origin URLs for named routes from the parameters the current page already
 * carries (the organization slug, the project slug), so a nav page or a palette action
 * needs no parameters passed by hand.
 *
 * @internal
 */
final class ShellUrls
{
    /**
     * A relative URL for `$name`, or null when the route does not exist or a required
     * parameter is neither given, nor on the current route, nor in `$context`.
     *
     * `$context` (route parameter => value, from the resolved context levels) fills a
     * parameter the current page's URL omits: a page under `/projects/{project}` still
     * links to `/{organization}/usage` when the organization is resolved another way.
     *
     * @param  array<string, string|int>  $parameters
     * @param  array<string, string|int>  $context
     */
    public static function route(Router $router, string $name, ?Route $current, array $parameters = [], array $context = []): ?string
    {
        $route = $router->getRoutes()->getByName($name);

        if (! $route instanceof Route) {
            return null;
        }

        return self::toRoute($route, self::parametersFor($route, $current, $parameters, $context));
    }

    /**
     * A relative URL for `$route` with exactly `$parameters`, or null when it cannot be built.
     *
     * @param  array<string, string|int>  $parameters
     */
    public static function toRoute(Route $route, array $parameters): ?string
    {
        foreach (self::requiredParameters($route) as $required) {
            if (! array_key_exists($required, $parameters) || $parameters[$required] === '') {
                return null;
            }
        }

        return URL::toRoute($route, $parameters, false);
    }

    /**
     * The target route's parameters: those the current route carries (raw, before model
     * binding), overridden by the explicit ones, limited to what the target uses. A
     * parameter neither carries is taken from `$context` (the resolved context levels).
     *
     * @param  array<string, string|int>  $parameters
     * @param  array<string, string|int>  $context
     * @return array<string, string|int>
     */
    public static function parametersFor(Route $route, ?Route $current, array $parameters = [], array $context = []): array
    {
        $names = self::parameterNames($route);
        $carried = $current instanceof Route ? self::rawParameters($current) : [];
        $out = [];

        foreach ($names as $name) {
            if (array_key_exists($name, $parameters)) {
                $out[$name] = $parameters[$name];
            } elseif (array_key_exists($name, $carried)) {
                $out[$name] = $carried[$name];
            } elseif (array_key_exists($name, $context) && $context[$name] !== '') {
                $out[$name] = $context[$name];
            }
        }

        // Explicit parameters the route does not declare become the query string.
        foreach ($parameters as $name => $value) {
            if (! in_array($name, $names, true)) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * The route's parameters as they were in the URL (before model binding).
     *
     * @return array<string, string|int>
     */
    public static function rawParameters(Route $route): array
    {
        try {
            $parameters = $route->originalParameters();
        } catch (\LogicException) {
            return [];
        }

        $out = [];

        foreach ($parameters as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * The route's parameter names (`{org}`, `{project}`), in URL order.
     *
     * @return list<string>
     */
    public static function parameterNames(Route $route): array
    {
        return array_values(array_filter($route->parameterNames(), is_string(...)));
    }

    /**
     * The route's parameters that are not optional (`{org}` but not `{tab?}`).
     *
     * @return list<string>
     */
    public static function requiredParameters(Route $route): array
    {
        preg_match_all('/\{(\w+?)\}/', $route->getDomain().$route->uri(), $matches);

        return array_values(array_filter(
            $matches[1],
            static fn (string $name): bool => ! array_key_exists($name, $route->defaults),
        ));
    }

    /** A same-origin path (with its query), or null. Never a scheme, a host, `//` or a backslash. */
    public static function samePath(mixed $path): ?string
    {
        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            && preg_match('/[\\\\\x00-\x20\x7F]/', $path) !== 1 && strlen($path) <= 2048 ? $path : null;
    }
}
