<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.create`: what the person can create from "+" and the palette, and the creatable
 * "+" opens on a page that names none (`page.create`). Left out of the prop when there is
 * nothing to create, so cbox-ui shows no "+".
 */
readonly class ShellCreate
{
    /**
     * @param  list<ShellCreatable>  $items
     */
    public function __construct(
        public array $items = [],
        public ?string $default = null,
    ) {}

    public function has(string $key): bool
    {
        foreach ($this->items as $item) {
            if ($item->key === $key) {
                return true;
            }
        }

        return false;
    }

    /** @return array{items: list<array<string, mixed>>, default?: string} */
    public function toArray(): array
    {
        $out = ['items' => array_map(static fn (ShellCreatable $item): array => $item->toArray(), $this->items)];

        if ($this->default !== null && $this->default !== '') {
            $out['default'] = $this->default;
        }

        return $out;
    }
}
