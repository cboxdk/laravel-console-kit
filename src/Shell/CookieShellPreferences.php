<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\ShellPreferenceStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The default {@see ShellPreferenceStore}: one cookie per preference, a year long. Read on
 * the server before first paint, so nothing shifts.
 *
 * Theme, rail and Help ({@see CLIENT_COOKIES}) are small, not secret, and the client applies
 * a change before its `PATCH /shell/preferences` returns: the service provider registers
 * them with `EncryptCookies::except`, and they are not HttpOnly, so the page can read and
 * write them itself. The reader ignores any value it does not recognise.
 *
 * The last context is server-only (HttpOnly, encrypted like any other cookie) and belongs
 * to one person: it carries a fingerprint of the person's id, and a cookie left by someone
 * else in the same browser (after "Switch account") is ignored.
 */
class CookieShellPreferences implements ShellPreferenceStore
{
    public const THEME = 'cbox_theme';

    public const RAIL = 'cbox_shell_rail';

    public const HELP = 'cbox_shell_help';

    public const LAST_CONTEXT = 'cbox_shell_last';

    /** The cookies the client may read and write itself, so they are not encrypted. */
    public const CLIENT_COOKIES = [self::THEME, self::RAIL, self::HELP];

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
        $person = self::person($request);
        $value = self::cookie($request, self::LAST_CONTEXT);

        if ($person === null || $value === null || ! str_contains($value, '|')) {
            return null;
        }

        [$owner, $path] = explode('|', $value, 2);

        return hash_equals($person, $owner) ? ShellUrls::samePath($path) : null;
    }

    public function rememberLastContext(Request $request, string $url): array
    {
        $person = self::person($request);
        $path = ShellUrls::samePath($url);

        return $person === null || $path === null
            ? []
            : [cookie(self::LAST_CONTEXT, $person.'|'.$path, self::MINUTES, null, null, null, true, false, 'lax')];
    }

    /** A fingerprint of the signed-in person's id, or null when nobody is signed in. */
    private static function person(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) && (string) $id !== '' ? substr(hash('sha256', (string) $id), 0, 32) : null;
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
