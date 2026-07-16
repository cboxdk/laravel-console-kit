<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

/**
 * Who the console request is for — so a plugin can resolve the current organization
 * and user without knowing the host app's own auth internals. The host binds its
 * implementation; a plugin reads `Console::context()`. Unbound, it resolves to a null
 * context (no org, no user, not admin) so the kit works with no host wiring.
 */
interface CurrentContext
{
    /** The organization the console is currently scoped to, or null. */
    public function organizationId(): ?string;

    /** The signed-in user (subject) id, or null. */
    public function userId(): ?string;

    /** Whether the current user administers the current organization. */
    public function isAdmin(): bool;
}
