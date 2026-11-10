<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Shell\ShellHelp;
use Illuminate\Http\Request;

/**
 * What the Help column needs for a request: whether an Assistant is behind it, the page's
 * help topic, and the Assistant URLs the browser calls after first paint. Resolve it from
 * config only; never call the Assistant here (local first, spec §11.2a).
 */
interface HelpConfig
{
    public function resolve(Request $request): ShellHelp;
}
