<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves a context switch to the URL the person lands on (spec §5.6). The URL is the
 * state: nothing is kept in the session.
 *
 * 1. The selected items are resolved through each level's `find()`; an unknown id is
 *    refused. Levels above the switched one keep the current selection. Levels below it
 *    keep an item with the same name if the new parent has one, else its first item
 *    ("Production" stays "Production" when switching project).
 * 2. When the page the switch came from carries the switched level in its URL and has no
 *    other parameters, it is rebuilt with the new slugs: the same page in the new context.
 * 3. A page with any parameter that is not a context level shows one record. It falls
 *    back to that area's list, so an entity id is never carried into another context.
 * 4. Otherwise the person lands on the deepest selected item's own start page.
 */
class ContextSwitcher
{
    public function __construct(
        private readonly ShellBuilder $shell,
        private readonly Router $router,
    ) {}

    /**
     * @param  array<string, string>  $select  level key => id of the newly chosen item(s)
     *
     * @throws ContextSwitchRefused
     */
    public function resolve(Request $request, string $from, array $select): SwitchResult
    {
        $levels = $this->shell->contextLevels();
        $first = null;

        foreach (array_keys($select) as $key) {
            $index = $this->shell->levelIndex($key);

            if ($index === null) {
                throw ContextSwitchRefused::because("Unknown context level [{$key}].");
            }

            $first = $first === null ? $index : min($first, $index);
        }

        if ($first === null) {
            throw ContextSwitchRefused::because('Nothing to switch to.');
        }

        $fromRequest = $this->requestFor($request, $from);
        $route = $fromRequest?->route();
        $route = $route instanceof Route ? $route : null;
        $current = $fromRequest instanceof Request ? $this->shell->resolveContext($fromRequest)->items : [];

        $items = [];

        foreach ($levels as $index => $level) {
            if (array_key_exists($level->key(), $select)) {
                $item = $level->find($request, $select[$level->key()], $items);

                if (! $item instanceof ContextItem) {
                    throw ContextSwitchRefused::because("No {$level->key()} [{$select[$level->key()]}] for this person.");
                }
            } elseif ($index < $first) {
                $item = $current[$index] ?? null;

                if (! $item instanceof ContextItem) {
                    throw ContextSwitchRefused::because("Switching {$levels[$first]->key()} needs a current {$level->key()}.");
                }
            } else {
                $item = $this->equivalent($request, $level, $items, $current[$index] ?? null);
            }

            if (! $item instanceof ContextItem) {
                break;
            }

            $items[] = $item;
        }

        return $this->target($route, $levels, $items, $first);
    }

    /**
     * @param  list<ContextLevel>  $levels
     * @param  list<ContextItem>  $items
     */
    private function target(?Route $route, array $levels, array $items, int $switched): SwitchResult
    {
        $deepest = count($items) - 1;
        $home = new SwitchResult(
            $levels[$deepest]->url($items[$deepest], array_slice($items, 0, $deepest)),
            SwitchResult::ITEM_HOME,
            $items,
        );

        if (! $route instanceof Route || ! in_array($levels[$switched]->routeParameter(), ShellUrls::parameterNames($route), true)) {
            return $home;
        }

        $contextParameters = [];

        foreach ($levels as $index => $level) {
            $contextParameters[$level->routeParameter()] = isset($items[$index]) ? $items[$index]->routeKey : null;
        }

        $entityPage = array_diff(ShellUrls::parameterNames($route), array_keys($contextParameters)) !== [];

        if (! $entityPage) {
            $url = ShellUrls::toRoute($route, $this->parametersFor($route, $contextParameters));

            return $url === null ? $home : new SwitchResult($url, SwitchResult::SAME_PAGE, $items);
        }

        $fallback = $this->shell->fallbackRouteFor($route);
        $list = $fallback !== null ? $this->router->getRoutes()->getByName($fallback) : null;

        if (! $list instanceof Route || array_diff(ShellUrls::parameterNames($list), array_keys($contextParameters)) !== []) {
            return $home;
        }

        $url = ShellUrls::toRoute($list, $this->parametersFor($list, $contextParameters));

        return $url === null ? $home : new SwitchResult($url, SwitchResult::LIST_FALLBACK, $items);
    }

    /**
     * @param  array<string, string|null>  $context
     * @return array<string, string>
     */
    private function parametersFor(Route $route, array $context): array
    {
        $out = [];

        foreach (ShellUrls::parameterNames($route) as $name) {
            if (isset($context[$name])) {
                $out[$name] = $context[$name];
            }
        }

        return $out;
    }

    /**
     * Under the new parent: the item named like the previous one, else the first.
     *
     * @param  list<ContextItem>  $ancestors
     */
    private function equivalent(Request $request, ContextLevel $level, array $ancestors, ?ContextItem $previous): ?ContextItem
    {
        if ($previous instanceof ContextItem) {
            foreach ($level->list($request, new ContextQuery($previous->name, $ancestors, null, 25))->items as $candidate) {
                if (mb_strtolower($candidate->name) === mb_strtolower($previous->name)) {
                    return $candidate;
                }
            }
        }

        return $level->list($request, new ContextQuery(null, $ancestors, null, 1))->items[0] ?? null;
    }

    /**
     * A GET request for the page the switch came from, matched to its route, carrying the
     * real request's user and session. Null when the path matches no route.
     */
    private function requestFor(Request $request, string $from): ?Request
    {
        $path = ShellUrls::samePath($from);

        if ($path === null) {
            return null;
        }

        $fromRequest = Request::create($request->getSchemeAndHttpHost().$path, 'GET', [], $request->cookies->all());
        $fromRequest->setUserResolver($request->getUserResolver());

        if ($request->hasSession()) {
            $fromRequest->setLaravelSession($request->session());
        }

        try {
            $route = $this->router->getRoutes()->match($fromRequest);
        } catch (HttpException) {
            return null;
        }

        $fromRequest->setRouteResolver(static fn (): Route => $route);

        return $fromRequest;
    }
}
