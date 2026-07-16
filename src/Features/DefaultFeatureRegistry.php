<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Features;

use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Closure;

final class DefaultFeatureRegistry implements FeatureRegistry
{
    /** @var array<string, bool|Closure(): bool> */
    private array $features = [];

    public function register(string $key, bool|Closure $enabled = true): void
    {
        $this->features[$key] = $enabled;
    }

    public function active(string $key): bool
    {
        if (! array_key_exists($key, $this->features)) {
            return false; // deny-by-default
        }

        $enabled = $this->features[$key];

        return $enabled instanceof Closure ? (bool) $enabled() : $enabled;
    }

    public function activeKeys(): array
    {
        return array_values(array_filter(
            array_keys($this->features),
            fn (string $key): bool => $this->active($key),
        ));
    }
}
