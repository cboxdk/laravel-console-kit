<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Illuminate\Http\Request;
use Throwable;

/**
 * The palette's server groups (spec §6.1): "Records" from the app's search provider, and
 * "Go to" (organizations, and level 2/3 combinations like "Webshop · Production" across
 * all the person's organizations) from the context levels. Each group is answered on its own, so the client can ask for them
 * in parallel and a slow or failing group never blocks the others: a group that throws is
 * reported and left out.
 *
 * "Go to" rows are plain links to the switch endpoint (GET), so Enter and ⌘Enter work.
 */
class ShellSearch
{
    public const RECORDS = 'records';

    public const GOTO = 'goto';

    /** How many of the person's level-1 items "Go to" searches level 2 under. */
    public const GOTO_PARENTS = 10;

    /** How many level-2 hits "Go to" expands into "Project · Environment" rows. */
    public const GOTO_EXPANDED = 3;

    public function __construct(private readonly ShellBuilder $shell) {}

    /**
     * @param  array<string, string>  $contextIds  level key => id the page is in
     * @param  list<string>|null  $groups  which groups; null for all
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public function search(Request $request, string $term, array $contextIds, string $from, ?array $groups = null, int $limit = 5): array
    {
        $context = $this->contextFrom($request, $contextIds);
        $out = [];

        foreach ([self::RECORDS, self::GOTO] as $group) {
            if ($groups !== null && ! in_array($group, $groups, true)) {
                continue;
            }

            try {
                $items = $group === self::RECORDS
                    ? $this->records($request, $term, $context, $limit)
                    : $this->goTo($request, $term, $context, $from, $limit);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $out[] = [
                'key' => $group,
                'label' => $group === self::RECORDS ? __('Records') : __('Go to'),
                'items' => array_map(static fn (SearchResult $result): array => $result->toArray(), $items),
            ];
        }

        return array_map(static fn (array $group): array => [
            'key' => $group['key'],
            'label' => is_string($group['label']) ? $group['label'] : $group['key'],
            'items' => $group['items'],
        ], $out);
    }

    /**
     * The context the page is in, re-resolved through `find()` (never trusted from the query).
     *
     * @param  array<string, string>  $ids
     * @return array<string, ContextItem>
     */
    private function contextFrom(Request $request, array $ids): array
    {
        $items = [];
        $list = [];

        foreach ($this->shell->contextLevels() as $level) {
            $id = $ids[$level->key()] ?? null;
            $item = is_string($id) && $id !== '' ? $level->find($request, $id, $list) : null;

            if (! $item instanceof ContextItem) {
                break;
            }

            $items[$level->key()] = $item;
            $list[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, ContextItem>  $context
     * @return list<SearchResult>
     */
    private function records(Request $request, string $term, array $context, int $limit): array
    {
        $provider = $this->shell->searchProvider();

        if ($provider === null || trim($term) === '') {
            return [];
        }

        return array_slice($provider->search($request, new SearchQuery($term, $context, $limit)), 0, $limit);
    }

    /**
     * Level 1 across the person's items, and level 2 (and 2 · 3) across all the level-1
     * items they belong to, the current one first (spec §6.1.4). Each level-2 row's hint
     * names its parent ("Project · Cbox").
     *
     * Bounded: level 2 is searched under at most {@see GOTO_PARENTS} level-1 items, and only
     * the first {@see GOTO_EXPANDED} level-2 hits are expanded into their level-3 items, so
     * one keystroke costs a fixed number of `list()` calls.
     *
     * @param  array<string, ContextItem>  $context
     * @return list<SearchResult>
     */
    private function goTo(Request $request, string $term, array $context, string $from, int $limit): array
    {
        $levels = $this->shell->contextLevels();
        $term = trim($term);

        if ($levels === [] || $term === '') {
            return [];
        }

        $results = [];
        $top = $levels[0];

        foreach ($top->list($request, new ContextQuery($term, [], null, $limit))->items as $item) {
            $results[] = new SearchResult(
                $top->key().':'.$item->id, $item->name, $this->switchUrl($from, [$top->key() => $item->id]), $top->label(),
            );
        }

        $second = $levels[1] ?? null;

        if ($second === null) {
            return array_slice($results, 0, max($limit, 1) * 3);
        }

        $third = $levels[2] ?? null;
        $expanded = 0;

        foreach ($this->parents($request, $top, $context[$top->key()] ?? null) as $parent) {
            foreach ($second->list($request, new ContextQuery($term, [$parent], null, $limit))->items as $item) {
                $select = [$top->key() => $parent->id, $second->key() => $item->id];
                $hint = $second->label().' · '.$parent->name;

                $results[] = new SearchResult(
                    $second->key().':'.$item->id, $item->name, $this->switchUrl($from, $select), $hint,
                );

                if ($third === null || $expanded >= self::GOTO_EXPANDED) {
                    continue;
                }

                $expanded++;

                foreach ($third->list($request, new ContextQuery(null, [$parent, $item], null, 5))->items as $leaf) {
                    $results[] = new SearchResult(
                        $second->key().':'.$item->id.'/'.$third->key().':'.$leaf->id,
                        $item->name.' · '.$leaf->name,
                        $this->switchUrl($from, [...$select, $third->key() => $leaf->id]),
                        $hint,
                    );
                }
            }
        }

        return array_slice($results, 0, max($limit, 1) * 3);
    }

    /**
     * The level-1 items whose level 2 is searched: the current one first, then the
     * person's others, at most {@see GOTO_PARENTS}.
     *
     * @return list<ContextItem>
     */
    private function parents(Request $request, ContextLevel $top, ?ContextItem $current): array
    {
        $parents = $current instanceof ContextItem ? [$current->id => $current] : [];

        foreach ($top->list($request, new ContextQuery(null, [], null, self::GOTO_PARENTS))->items as $item) {
            if (count($parents) >= self::GOTO_PARENTS) {
                break;
            }

            $parents[$item->id] ??= $item;
        }

        return array_values($parents);
    }

    /** @param array<string, string> $select */
    private function switchUrl(string $from, array $select): string
    {
        return route(ShellBuilder::ROUTE_SWITCH, ['select' => $select, 'from' => $from], false);
    }
}
