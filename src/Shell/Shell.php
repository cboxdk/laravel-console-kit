<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * The `shell` Inertia prop, field for field the `Shell` type cbox-ui exports (spec §11.1).
 * {@see toArray()} is the wire shape; everything else stays on the server.
 *
 * `new Shell` is a valid, empty shell, so a test can build one without the builder.
 */
readonly class Shell
{
    public function __construct(
        public ShellApp $app = new ShellApp,
        public ShellPerson $person = new ShellPerson,
        public ShellNav $nav = new ShellNav,
        public ShellContext $context = new ShellContext,
        public ShellPalette $palette = new ShellPalette,
        public ShellHelp $help = new ShellHelp,
        public ShellNotifications $notifications = new ShellNotifications,
        public ShellApps $apps = new ShellApps,
        public ?ShellBanner $banner = null,
        public ShellPreferences $preferences = new ShellPreferences,
        public ?ShellAccount $account = null,
        /**
         * Every strip under the header: the sandbox strip first, then the app's banners. Sent as
         * `banners` when not empty; `banner` stays for cbox-ui 0.10.1 and older.
         *
         * @var list<ShellBanner>
         */
        public array $banners = [],
        /** The page the request is on (cbox-ui 0.11). Sent when the app declared the page or the shell can create. */
        public ?ShellPage $page = null,
        /** What "+" and the palette can create (cbox-ui 0.11). Sent when it has items. */
        public ?ShellCreate $create = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'app' => $this->app->toArray(),
            'person' => $this->person->toArray(),
        ];

        if ($this->page instanceof ShellPage) {
            $out['page'] = $this->page->toArray();
        }

        $out += [
            'nav' => $this->nav->toArray(),
            'context' => $this->context->toArray(),
            'palette' => $this->palette->toArray(),
        ];

        if ($this->create instanceof ShellCreate && $this->create->items !== []) {
            $out['create'] = $this->create->toArray();
        }

        $out += [
            'help' => $this->help->toArray(),
            'notifications' => $this->notifications->toArray(),
            'apps' => $this->apps->toArray(),
        ];

        if ($this->account instanceof ShellAccount && ! $this->account->isEmpty()) {
            $out['account'] = $this->account->toArray();
        }

        if ($this->banner instanceof ShellBanner) {
            $out['banner'] = $this->banner->toArray();
        }

        if ($this->banners !== []) {
            $out['banners'] = array_map(static fn (ShellBanner $banner): array => $banner->toArray(), $this->banners);
        }

        $out['preferences'] = $this->preferences->toArray();

        return $out;
    }
}
