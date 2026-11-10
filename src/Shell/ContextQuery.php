<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * What a context menu asks a level for: one page of its items under `ancestors`
 * (the selected organization for a project level, organization and project for an
 * environment level), optionally narrowed by a search term.
 */
readonly class ContextQuery
{
    /**
     * @param  list<ContextItem>  $ancestors  outermost first; the last one is the parent
     */
    public function __construct(
        public ?string $search = null,
        public array $ancestors = [],
        public ?string $cursor = null,
        public int $limit = 25,
    ) {}

    /** The immediate parent (the last ancestor), or null for the top level. */
    public function parent(): ?ContextItem
    {
        return $this->ancestors === [] ? null : $this->ancestors[count($this->ancestors) - 1];
    }
}
