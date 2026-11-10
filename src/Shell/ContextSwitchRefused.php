<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use RuntimeException;

/** A switch named an unknown level, or an item the person may not open. */
class ContextSwitchRefused extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
