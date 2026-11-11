<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * A record the palette suggests under "Recent" before anything is typed (`palette.recent`,
 * cbox-ui 0.11): the invoice the person opened last, the customer they edit most.
 */
readonly class PaletteRecord
{
    public function __construct(
        public string $id = '',
        public string $title = '',
        public string $url = '',
        public ?string $subtitle = null,
    ) {}

    /** @return array{id: string, title: string, url: string, subtitle?: string} */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'title' => $this->title, 'url' => $this->url];

        if ($this->subtitle !== null && $this->subtitle !== '') {
            $out['subtitle'] = $this->subtitle;
        }

        return $out;
    }
}
