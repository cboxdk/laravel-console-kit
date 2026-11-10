<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Testing;

use Cbox\Console\Kit\Contracts\NotificationSource;
use Cbox\Console\Kit\Shell\NotificationItem;
use Illuminate\Http\Request;

/** An in-memory {@see NotificationSource}: items newest first, marked read in place. */
class FakeNotificationSource implements NotificationSource
{
    /** @var array<string, NotificationItem> */
    private array $items = [];

    public function with(NotificationItem ...$items): static
    {
        foreach ($items as $item) {
            $this->items[$item->id] = $item;
        }

        return $this;
    }

    public function unread(Request $request): int
    {
        return count(array_filter($this->items, static fn (NotificationItem $item): bool => ! $item->read));
    }

    public function latest(Request $request, int $limit = 20): array
    {
        $items = array_values($this->items);
        usort($items, static fn (NotificationItem $a, NotificationItem $b): int => $b->createdAt <=> $a->createdAt);

        return array_slice($items, 0, $limit);
    }

    public function markRead(Request $request, ?string $id = null): void
    {
        foreach ($this->items as $key => $item) {
            if ($id === null || $item->id === $id) {
                $this->items[$key] = new NotificationItem($item->id, $item->title, $item->createdAt, true, $item->body, $item->url);
            }
        }
    }
}
