<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Cbox\Console\Kit\Shell\ShellBuilder;
use Cbox\Console\Kit\Shell\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** `PATCH /shell/preferences` `{theme?, railPinned?, helpOpen?}` → 204, saved per person. */
class ShellPreferencesController
{
    public function __invoke(Request $request, ShellBuilder $shell): Response
    {
        $request->validate([
            'theme' => ['sometimes', 'string', 'in:system,light,dark'],
            'railPinned' => ['sometimes', 'boolean'],
            'helpOpen' => ['sometimes', 'boolean'],
        ]);

        $theme = ShellInput::string($request, 'theme', 16);
        $response = response()->noContent();

        foreach ($shell->preferenceStore()->write(
            $request,
            $theme !== null ? Theme::tryFrom($theme) : null,
            ShellInput::bool($request, 'railPinned'),
            ShellInput::bool($request, 'helpOpen'),
        ) as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
