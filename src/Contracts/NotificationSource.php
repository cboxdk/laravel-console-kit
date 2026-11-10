<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Shell\NotificationItem;
use Illuminate\Http\Request;

/**
 * Where the bell's product events come from (spec §8). Without one the bell is not
 * rendered at all. Answer from local data; the unread count is read on every page.
 */
interface NotificationSource
{
    public function unread(Request $request): int;

    /**
     * The newest items, newest first.
     *
     * @return list<NotificationItem>
     */
    public function latest(Request $request, int $limit = 20): array;

    /** Mark one item read, or all of them when `$id` is null. */
    public function markRead(Request $request, ?string $id = null): void;
}
