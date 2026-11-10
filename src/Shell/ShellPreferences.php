<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * `shell.preferences`: read on the server before first paint, so theme, rail pin and
 * Help open are applied without a layout shift (spec §12.1).
 */
readonly class ShellPreferences
{
    public function __construct(
        public Theme $theme = Theme::System,
        public string $locale = 'en',
        public bool $railPinned = false,
        public bool $helpOpen = false,
    ) {}

    /** @return array{theme: string, locale: string, railPinned: bool, helpOpen: bool} */
    public function toArray(): array
    {
        return [
            'theme' => $this->theme->value,
            'locale' => $this->locale,
            'railPinned' => $this->railPinned,
            'helpOpen' => $this->helpOpen,
        ];
    }
}
