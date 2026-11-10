<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** What the palette asks a search provider: the term, in the current context. */
readonly class SearchQuery
{
    /**
     * @param  array<string, ContextItem>  $context  the selected items, keyed by level key, outermost first
     */
    public function __construct(
        public string $term = '',
        public array $context = [],
        public int $limit = 5,
    ) {}
}
