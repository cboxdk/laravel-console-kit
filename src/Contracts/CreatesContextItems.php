<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\CreatedContextItem;
use Illuminate\Http\Request;

/**
 * A {@see ContextLevel} that offers "New …" inline in its menu (spec §5.7): the menu asks
 * only for a name, the item is created and selected, and the person lands on its details
 * page. Creating is something the person clicked and waits for, so it may call another
 * service (Cbox ID, for an organization) with a short timeout.
 */
interface CreatesContextItems
{
    /**
     * @param  list<ContextItem>  $ancestors
     */
    public function create(Request $request, string $name, array $ancestors): CreatedContextItem;
}
