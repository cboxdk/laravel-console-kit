<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\HelpConfig;
use Illuminate\Http\Request;

/**
 * A {@see HelpConfig} from fixed values, typically the app's Assistant config:
 *
 *     Console::shell()->help(new StaticHelpConfig(
 *         sessionUrl: '/assistant/session',
 *         portalUrl: config('services.assistant.portal_url'),
 *         updatesUrl: '/assistant/support/updates',
 *     ));
 *
 * Enabled unless told otherwise. The page's topic comes from `helpTopic()` on the builder.
 */
readonly class StaticHelpConfig implements HelpConfig
{
    public function __construct(
        public ?string $sessionUrl = null,
        public ?string $portalUrl = null,
        public ?string $updatesUrl = null,
        public bool $enabled = true,
    ) {}

    public function resolve(Request $request): ShellHelp
    {
        return new ShellHelp($this->enabled, null, $this->sessionUrl, $this->portalUrl, $this->updatesUrl);
    }
}
