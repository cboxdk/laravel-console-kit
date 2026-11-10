<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** Where a context switch lands, and why. */
readonly class SwitchResult
{
    /** The same page exists in the new context. */
    public const SAME_PAGE = 'same_page';

    /** The page showed one record; it fell back to that area's list (spec §5.6). */
    public const LIST_FALLBACK = 'list_fallback';

    /** No equivalent page: the deepest selected item's own start page. */
    public const ITEM_HOME = 'item_home';

    /**
     * @param  list<ContextItem>  $items  the new selection, outermost first
     */
    public function __construct(
        public string $url,
        public string $resolution,
        public array $items = [],
    ) {}
}
