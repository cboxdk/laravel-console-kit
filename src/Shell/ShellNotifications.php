<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.notifications`: `{enabled: false}` when the app has no notification source (the
 * bell is not rendered at all, spec §8), else the unread count and the bell's endpoint.
 */
readonly class ShellNotifications
{
    public function __construct(
        public bool $enabled = false,
        public int $unread = 0,
        public string $url = '',
    ) {}

    public static function disabled(): self
    {
        return new self;
    }

    public static function enabled(int $unread, string $url): self
    {
        return new self(true, max(0, $unread), $url);
    }

    /** @return array{enabled: false}|array{enabled: true, unread: int, url: string} */
    public function toArray(): array
    {
        if (! $this->enabled) {
            return ['enabled' => false];
        }

        return ['enabled' => true, 'unread' => $this->unread, 'url' => $this->url];
    }
}
