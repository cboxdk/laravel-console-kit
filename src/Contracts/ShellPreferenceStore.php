<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Shell\ShellPreferences;
use Cbox\Console\Kit\Shell\Theme;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The per-person shell preferences (theme, rail pinned, Help open) and the last used
 * context. Read on the server before first paint. The default keeps them in cookies; an
 * app that keeps them elsewhere binds its own.
 */
interface ShellPreferenceStore
{
    public function read(Request $request): ShellPreferences;

    /**
     * Save the changed preferences. Null leaves one as it is.
     *
     * @return list<Cookie> cookies to attach to the response, if the store uses any
     */
    public function write(Request $request, ?Theme $theme = null, ?bool $railPinned = null, ?bool $helpOpen = null): array;

    /** The same-origin URL of the context the person last switched to, if known. */
    public function lastContext(Request $request): ?string;

    /** @return list<Cookie> */
    public function rememberLastContext(Request $request, string $url): array;
}
