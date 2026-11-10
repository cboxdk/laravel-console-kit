<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\ShellPreferenceStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The default {@see ShellPreferenceStore}: one cookie per preference, a year long,
 * HttpOnly off so the client can apply a change before the round trip ends. Read on the
 * server before first paint, so nothing shifts.
 *
 * The values are small and not secret. If the app excludes them from cookie encryption
 * (`EncryptCookies::except`) the client may also write them itself; the reader accepts
 * both forms and ignores anything it does not recognise.
 */
class CookieShellPreferences implements ShellPreferenceStore
{
    public const THEME = 'cbox_theme';

    public const RAIL = 'cbox_shell_rail';

    public const HELP = 'cbox_shell_help';

    public const LAST_CONTEXT = 'cbox_shell_last';

    private const MINUTES = 60 * 24 * 365;

    public function read(Request $request): ShellPreferences
    {
        return new ShellPreferences(
            Theme::tryFrom(self::cookie($request, self::THEME) ?? '') ?? Theme::System,
            app()->getLocale(),
            self::cookie($request, self::RAIL) === '1',
            self::cookie($request, self::HELP) === '1',
        );
    }

    public function write(Request $request, ?Theme $theme = null, ?bool $railPinned = null, ?bool $helpOpen = null): array
    {
        $cookies = [];

        if ($theme instanceof Theme) {
            $cookies[] = self::make(self::THEME, $theme->value);
        }

        if ($railPinned !== null) {
            $cookies[] = self::make(self::RAIL, $railPinned ? '1' : '0');
        }

        if ($helpOpen !== null) {
            $cookies[] = self::make(self::HELP, $helpOpen ? '1' : '0');
        }

        return $cookies;
    }

    public function lastContext(Request $request): ?string
    {
        return ShellUrls::samePath(self::cookie($request, self::LAST_CONTEXT));
    }

    public function rememberLastContext(Request $request, string $url): array
    {
        $path = ShellUrls::samePath($url);

        return $path === null ? [] : [self::make(self::LAST_CONTEXT, $path)];
    }

    private static function cookie(Request $request, string $name): ?string
    {
        $value = $request->cookies->get($name);

        return is_string($value) ? $value : null;
    }

    /** @param non-empty-string $name */
    private static function make(string $name, string $value): Cookie
    {
        return cookie($name, $value, self::MINUTES, null, null, null, false, false, 'lax');
    }
}
