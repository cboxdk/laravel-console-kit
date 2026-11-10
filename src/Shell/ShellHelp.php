<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.help`: whether the Help column has an Assistant behind it, the page's help
 * topic, and the Assistant URLs the browser calls after first paint. Nothing here is
 * fetched on the server (local first, spec §11.2a).
 */
readonly class ShellHelp
{
    public function __construct(
        public bool $enabled = false,
        public ?string $topic = null,
        public ?string $sessionUrl = null,
        public ?string $portalUrl = null,
        public ?string $updatesUrl = null,
    ) {}

    /** @return array{enabled: bool, topic?: string, sessionUrl?: string, portalUrl?: string, updatesUrl?: string} */
    public function toArray(): array
    {
        $out = ['enabled' => $this->enabled];

        foreach (['topic' => $this->topic, 'sessionUrl' => $this->sessionUrl, 'portalUrl' => $this->portalUrl, 'updatesUrl' => $this->updatesUrl] as $key => $value) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
