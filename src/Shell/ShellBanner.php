<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.banner`: the strip under the header. The sandbox strip is added by the kit when
 * the current environment has `testData: true`; an app can send its own (impersonation,
 * a notice) and that one wins.
 */
readonly class ShellBanner
{
    public function __construct(
        public BannerKind $kind,
        public string $text,
        public ?ShellLink $action = null,
    ) {}

    /** @return array{kind: string, text: string, action?: array{label: string, href: string}} */
    public function toArray(): array
    {
        $out = ['kind' => $this->kind->value, 'text' => $this->text];

        if ($this->action instanceof ShellLink) {
            $out['action'] = $this->action->toArray();
        }

        return $out;
    }
}
