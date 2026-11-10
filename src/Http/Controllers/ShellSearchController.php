<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Cbox\Console\Kit\Shell\ShellSearch;
use Cbox\Console\Kit\Shell\ShellUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /shell/search?q=&group=records|goto&context[…]=…&from=` → `{groups: [{key, label, items}]}`.
 * Without `group` every server group is answered; with it, one, so the palette can ask
 * in parallel.
 */
class ShellSearchController
{
    public function __invoke(Request $request, ShellSearch $search): JsonResponse
    {
        $group = ShellInput::string($request, 'group', 32);

        $groups = $search->search(
            $request,
            ShellInput::string($request, 'q', 200) ?? '',
            ShellInput::map($request, 'context'),
            ShellUrls::samePath($request->input('from')) ?? '/',
            $group === null ? null : [$group],
            ShellInput::limit($request, 5, 20),
        );

        return response()->json(['groups' => $groups])->header('Cache-Control', 'private, no-store');
    }
}
