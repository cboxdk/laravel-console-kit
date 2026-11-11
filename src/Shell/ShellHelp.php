<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.help`: whether the Help column has an Assistant behind it, the page's help
 * topic, and the Assistant URLs the browser calls after first paint. Nothing here is
 * fetched on the server (local first, spec §11.2a).
 *
 * `articles` are the page's articles for an app without an Assistant articles endpoint;
 * `unread` is the number of unread support replies the app already knows, so Help shows
 * its dot before the updates have loaded (both cbox-ui 0.11).
 */
readonly class ShellHelp
{
    public function __construct(
        public bool $enabled = false,
        public ?string $topic = null,
        public ?string $sessionUrl = null,
        public ?string $portalUrl = null,
        public ?string $updatesUrl = null,
        /** @var list<ShellHelpArticle> */
        public array $articles = [],
        public ?int $unread = null,
    ) {}

    /**
     * This help with another topic, articles and unread count; null keeps what it has.
     *
     * @param  list<ShellHelpArticle>|null  $articles
     */
    public function with(?string $topic = null, ?array $articles = null, ?int $unread = null): self
    {
        return new self(
            $this->enabled,
            $topic ?? $this->topic,
            $this->sessionUrl,
            $this->portalUrl,
            $this->updatesUrl,
            $articles ?? $this->articles,
            $unread ?? $this->unread,
        );
    }

    /** @return array{enabled: bool, topic?: string, sessionUrl?: string, portalUrl?: string, updatesUrl?: string, articles?: list<array<string, string>>, unread?: int} */
    public function toArray(): array
    {
        $out = ['enabled' => $this->enabled];

        foreach (['topic' => $this->topic, 'sessionUrl' => $this->sessionUrl, 'portalUrl' => $this->portalUrl, 'updatesUrl' => $this->updatesUrl] as $key => $value) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        if ($this->articles !== []) {
            $out['articles'] = array_map(static fn (ShellHelpArticle $article): array => $article->toArray(), $this->articles);
        }

        if ($this->unread !== null && $this->unread > 0) {
            $out['unread'] = $this->unread;
        }

        return $out;
    }
}
