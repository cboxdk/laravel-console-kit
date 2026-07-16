<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Slots;

use Cbox\Console\Kit\Contracts\SlotRegistry;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

final class DefaultSlotRegistry implements SlotRegistry
{
    /** @var array<string, list<array{content: string|Closure, order: int}>> */
    private array $slots = [];

    public function __construct(private readonly ViewFactory $views) {}

    public function add(string $slot, string|Closure $content, int $order = 100): void
    {
        $this->slots[$slot][] = ['content' => $content, 'order' => $order];
    }

    public function has(string $slot): bool
    {
        return ($this->slots[$slot] ?? []) !== [];
    }

    public function render(string $slot, array $data = []): string
    {
        $items = $this->slots[$slot] ?? [];
        if ($items === []) {
            return '';
        }

        usort($items, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $html = '';
        foreach ($items as $item) {
            $content = $item['content'];
            $rendered = $content instanceof Closure ? $content($data) : $this->views->make($content, $data);
            $html .= $this->toHtml($rendered);
        }

        return $html;
    }

    private function toHtml(mixed $rendered): string
    {
        return match (true) {
            $rendered instanceof Htmlable => $rendered->toHtml(),
            $rendered instanceof View => $rendered->render(),
            is_string($rendered) => $rendered,
            default => '',
        };
    }
}
