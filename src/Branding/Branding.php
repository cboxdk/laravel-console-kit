<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Branding;

/**
 * A validated, immutable branding value object — a safe CSS custom-property map
 * plus a logo, favicon and app name. It is the ONLY thing the shell interpolates
 * into a `<style>` tag, so it validates every token server-side and emits nothing
 * it cannot prove safe (deny-by-default):
 *
 *  - a token NAME must be a plain CSS custom property (`--primary`, `--ring`);
 *  - a token VALUE must be a colour or simple functional notation (`#0a2540`,
 *    `oklch(0.45 0.16 258)`) — anything carrying `;`, `{}`, `<`, `@`, quotes,
 *    `url(...)`, a comment or an escape is dropped, never echoed.
 *
 * Because construction filters, a Branding can never carry an unsafe token, so
 * {@see styleTag()} / {@see cssVariables()} are safe to echo unescaped. Asset URLs
 * are likewise restricted to same-origin/relative, http(s) or `data:image/`.
 */
final readonly class Branding
{
    /** @var array<string, string> validated `--token` => value pairs */
    public array $tokens;

    public ?string $logoUrl;

    public ?string $faviconUrl;

    public ?string $appName;

    /**
     * @param  array<string, string>  $tokens  raw `--token` => value pairs (filtered here)
     */
    public function __construct(
        array $tokens = [],
        ?string $logoUrl = null,
        ?string $faviconUrl = null,
        ?string $appName = null,
    ) {
        $this->tokens = self::sanitizeTokens($tokens);
        $this->logoUrl = self::sanitizeUrl($logoUrl);
        $this->faviconUrl = self::sanitizeUrl($faviconUrl);
        $this->appName = self::sanitizeText($appName);
    }

    /** The empty branding — the shell falls back to its static CSS. */
    public static function empty(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->tokens === []
            && $this->logoUrl === null
            && $this->faviconUrl === null
            && $this->appName === null;
    }

    /**
     * The validated token map.
     *
     * @return array<string, string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * The custom-property declarations (`--primary:#0a2540;--ring:#0a2540`) — safe
     * to drop into a `style="..."` attribute. Empty string when there are no tokens.
     */
    public function cssVariables(): string
    {
        $out = '';
        foreach ($this->tokens as $name => $value) {
            $out .= $name.':'.$value.';';
        }

        return rtrim($out, ';');
    }

    /**
     * A ready-to-echo `<style>:root{...}</style>` scoping the tokens to `:root`, so a
     * plugin's palette overrides the shell's defaults. Empty string when there are no
     * tokens (nothing is injected). Safe to echo unescaped — every value was validated.
     */
    public function styleTag(): string
    {
        if ($this->tokens === []) {
            return '';
        }

        return '<style>:root{'.$this->cssVariables().'}</style>';
    }

    /**
     * Keep only tokens whose name is a plain CSS custom property and whose value is a
     * colour / simple functional notation. Everything else is dropped. Typed wide on
     * purpose: the input is often decoded from JSON/DB, so non-string keys or values
     * are guarded rather than assumed away.
     *
     * @param  array<array-key, mixed>  $tokens
     * @return array<string, string>
     */
    private static function sanitizeTokens(array $tokens): array
    {
        $clean = [];
        foreach ($tokens as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                continue;
            }

            $name = trim($name);
            $value = trim($value);

            if (self::isSafeName($name) && self::isSafeValue($value)) {
                $clean[$name] = $value;
            }
        }

        return $clean;
    }

    /** A CSS custom-property identifier: `--` then lowercase letter, then `[a-z0-9-]`. */
    private static function isSafeName(string $name): bool
    {
        return preg_match('/^--[a-z][a-z0-9-]*$/', $name) === 1;
    }

    /**
     * Deny-by-default value guard. Rejects any structural/injection character or
     * construct; allows only the charset colours and simple functional notation need.
     */
    private static function isSafeValue(string $value): bool
    {
        if ($value === '' || strlen($value) > 64) {
            return false;
        }

        // Structural or quoting characters that could break out of the declaration.
        if (preg_match('/[;{}<>@"\'`\\\\]/', $value) === 1) {
            return false;
        }

        // Constructs that can smuggle behaviour even within the allowed charset.
        $lower = strtolower($value);
        if (str_contains($lower, 'url') || str_contains($lower, 'expression')
            || str_contains($lower, 'javascript') || str_contains($value, '/*')) {
            return false;
        }

        // Allow-list: hex (#), functional notation (parens), numbers, units, spaces,
        // and the `/` used for alpha in `oklch(l c h / a)`.
        return preg_match('~^[a-z0-9#()%.,/ _-]+$~i', $value) === 1;
    }

    /** Restrict asset URLs to same-origin/relative, http(s), or a `data:image/` URI. */
    private static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $lower = strtolower($url);
        if (str_starts_with($lower, 'https://') || str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'data:image/')) {
            return $url;
        }

        return null;
    }

    private static function sanitizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, 120);
    }
}
