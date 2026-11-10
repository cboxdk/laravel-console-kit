<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\ContextLevel;

/**
 * The context a request is in: each shown level with its selected item, outermost first.
 * A page outside a level (no organization on `/orgs`) stops the chain there.
 */
readonly class ResolvedContext
{
    /**
     * @param  list<ContextLevel>  $levels
     * @param  list<ContextItem>  $items  the selected item of each of `$levels`, same order
     */
    public function __construct(
        public array $levels = [],
        public array $items = [],
    ) {}

    /** @return array<string, ContextItem> keyed by level key */
    public function byKey(): array
    {
        $out = [];

        foreach ($this->items as $index => $item) {
            $level = $this->levels[$index] ?? null;

            if ($level instanceof ContextLevel) {
                $out[$level->key()] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, string> level key => item id */
    public function ids(): array
    {
        return array_map(static fn (ContextItem $item): string => $item->id, $this->byKey());
    }

    /** The deepest selected item, or null. */
    public function deepest(): ?ContextItem
    {
        return $this->items === [] ? null : $this->items[count($this->items) - 1];
    }
}
