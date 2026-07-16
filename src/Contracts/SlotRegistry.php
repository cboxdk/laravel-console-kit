<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Closure;

/**
 * Named UI injection points. The host renders a slot (`@consoleSlot('dashboard.cards')`)
 * inside its own pages; a plugin contributes content to that slot — a view name, or a
 * closure returning a view/HTML — so it can drop a widget or panel into an EXISTING
 * page, not only add new routes.
 */
interface SlotRegistry
{
    /**
     * Contribute content to a slot. `$content` is a view name, or a
     * `Closure(array $data): string|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Support\Htmlable`.
     *
     * @param  string|Closure(array<string, mixed>): mixed  $content
     */
    public function add(string $slot, string|Closure $content, int $order = 100): void;

    /** Whether any content is registered for a slot. */
    public function has(string $slot): bool;

    /**
     * Render every contribution to a slot to HTML, in order.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $slot, array $data = []): string;
}
