<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** One page of a level's items, in the order the app wants them shown, and the cursor to the next. */
readonly class ContextPage
{
    /**
     * @param  list<ContextItem>  $items
     */
    public function __construct(
        public array $items = [],
        public ?string $nextCursor = null,
    ) {}

    /** @return array{object: string, data: list<array<string, mixed>>, has_more: bool, next_cursor: string|null} */
    public function toArray(): array
    {
        return [
            'object' => 'list',
            'data' => array_map(static fn (ContextItem $item): array => $item->toArray(), $this->items),
            'has_more' => $this->nextCursor !== null,
            'next_cursor' => $this->nextCursor,
        ];
    }
}
