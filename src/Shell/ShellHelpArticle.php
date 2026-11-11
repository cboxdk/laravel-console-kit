<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * An article the app hands Help with the page (`help.articles`, cbox-ui 0.11), for an app
 * without an Assistant articles endpoint. From local data only: never fetched from another
 * service while the page is built (spec §11.2a).
 */
readonly class ShellHelpArticle
{
    public function __construct(
        public string $id = '',
        public string $title = '',
        public ?string $excerpt = null,
        public ?string $body = null,
        public ?string $url = null,
    ) {}

    /** @return array{id: string, title: string, excerpt?: string, body?: string, url?: string} */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'title' => $this->title];

        foreach (['excerpt' => $this->excerpt, 'body' => $this->body, 'url' => $this->url] as $key => $value) {
            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
