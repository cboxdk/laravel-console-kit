<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Reads the shell endpoints' loosely typed input into typed values. Anything malformed is
 * dropped, never trusted.
 *
 * @internal
 */
final class ShellInput
{
    /** @return array<string, string> */
    public static function map(Request $request, string $key): array
    {
        $value = $request->input($key);
        $out = [];

        if (! is_array($value)) {
            return $out;
        }

        foreach ($value as $name => $id) {
            if (is_string($name) && is_string($id) && $id !== '' && strlen($id) <= 255) {
                $out[$name] = $id;
            }
        }

        return $out;
    }

    /**
     * The top-level keys of the request that name one of `$keys`, each a non-empty string.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public static function levels(Request $request, array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $id = $request->input($key);

            if (is_string($id) && $id !== '' && strlen($id) <= 255) {
                $out[$key] = $id;
            }
        }

        return $out;
    }

    public static function string(Request $request, string $key, int $max = 255): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' && mb_strlen($value) <= $max ? $value : null;
    }

    public static function bool(Request $request, string $key): ?bool
    {
        if (! $request->has($key)) {
            return null;
        }

        return filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public static function limit(Request $request, int $default, int $max): int
    {
        $value = filter_var($request->input('limit'), FILTER_VALIDATE_INT);

        return is_int($value) ? max(1, min($max, $value)) : $default;
    }
}
