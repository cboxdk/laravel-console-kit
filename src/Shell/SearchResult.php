<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/** One palette row: a record, or a "Go to" target. `url` is same-origin and opens with Enter. */
readonly class SearchResult
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public ?string $subtitle = null,
        public ?string $icon = null,
    ) {}

    /** @return array{id: string, title: string, url: string, subtitle?: string, icon?: string} */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'title' => $this->title, 'url' => $this->url];

        if ($this->subtitle !== null) {
            $out['subtitle'] = $this->subtitle;
        }

        if ($this->icon !== null) {
            $out['icon'] = $this->icon;
        }

        return $out;
    }
}
