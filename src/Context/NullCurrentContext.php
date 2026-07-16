<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Context;

use Cbox\Console\Kit\Contracts\CurrentContext;

/**
 * The default {@see CurrentContext} when a host binds none — no org, no user, not
 * admin. Keeps the kit usable standalone; the host overrides it with a real binding.
 */
final class NullCurrentContext implements CurrentContext
{
    public function organizationId(): ?string
    {
        return null;
    }

    public function userId(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return false;
    }
}
