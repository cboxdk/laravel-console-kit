<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Cbox\Console\Kit\Shell\ContextSwitcher;
use Cbox\Console\Kit\Shell\ContextSwitchRefused;
use Cbox\Console\Kit\Shell\ShellBuilder;
use Cbox\Console\Kit\Shell\ShellUrls;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET|POST /shell/switch?select[project]=…&from=/orgs/cbox/…` — resolve a context switch
 * and send the person there (303), or answer `{url, resolution}` to a JSON request. GET
 * makes palette "Go to" rows plain links. The landing URL is remembered as the last
 * context, so `/` can land there.
 *
 * Deprecated, accepted through 0.2.x: the flat body cbox-ui 0.10.3 and older post,
 * `{organization: id, project: id, …, return_to: path}`. Only top-level keys that name a
 * declared level are read, and only when the request carries no `select`.
 */
class ShellSwitchController
{
    public function __invoke(Request $request, ContextSwitcher $switcher, ShellBuilder $shell): Response
    {
        $select = ShellInput::map($request, 'select');
        $from = $request->input('from');

        if ($select === [] && ! $request->has('select')) {
            $select = ShellInput::levels($request, array_map(static fn ($level): string => $level->key(), $shell->contextLevels()));
            $from ??= $request->input('return_to');
        }

        $from = ShellUrls::samePath($from) ?? '/';

        try {
            $result = $switcher->resolve($request, $from, $select);
        } catch (ContextSwitchRefused $e) {
            abort(404, $e->getMessage());
        }

        $response = $request->expectsJson() && ! $request->hasHeader('X-Inertia')
            ? response()->json(['url' => $result->url, 'resolution' => $result->resolution])
            : redirect($result->url, 303);

        foreach ($shell->preferenceStore()->rememberLastContext($request, $result->url) as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
