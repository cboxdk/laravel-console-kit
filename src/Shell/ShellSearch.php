<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Illuminate\Http\Request;
use Throwable;

/**
 * The palette's server groups (spec §6.1): "Records" from the app's search provider, and
 * "Go to" (organizations, and level 2/3 combinations like "Webshop · Production") from
 * the context levels. Each group is answered on its own, so the client can ask for them
 * in parallel and a slow or failing group never blocks the others: a group that throws is
 * reported and left out.
 *
 * "Go to" rows are plain links to the switch endpoint (GET), so Enter and ⌘Enter work.
 */
class ShellSearch
{
    public const RECORDS = 'records';

    public const GOTO = 'goto';

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
     * Level 1 across the person's items; level 2 (and 2 · 3) under the current level 1.
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

        $parent = $context[$top->key()] ?? null;
        $second = $levels[1] ?? null;

        if ($parent instanceof ContextItem && $second !== null) {
            $third = $levels[2] ?? null;

            foreach ($second->list($request, new ContextQuery($term, [$parent], null, $limit))->items as $item) {
                $results[] = new SearchResult(
                    $second->key().':'.$item->id, $item->name, $this->switchUrl($from, [$second->key() => $item->id]),
                    $second->label().' · '.$parent->name,
                );

                if ($third === null) {
                    continue;
                }

                foreach ($third->list($request, new ContextQuery(null, [$parent, $item], null, 5))->items as $leaf) {
                    $results[] = new SearchResult(
                        $second->key().':'.$item->id.'/'.$third->key().':'.$leaf->id,
                        $item->name.' · '.$leaf->name,
                        $this->switchUrl($from, [$second->key() => $item->id, $third->key() => $leaf->id]),
                        $second->label().' · '.$parent->name,
                    );
                }
            }
        }

        return array_slice($results, 0, max($limit, 1) * 3);
    }

    /** @param array<string, string> $select */
    private function switchUrl(string $from, array $select): string
    {
        return route(ShellBuilder::ROUTE_SWITCH, ['select' => $select, 'from' => $from], false);
    }
}
