<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Cbox\Console\Kit\Shell\ShellHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** `GET /` — land on the person's last context (see {@see ShellHome}). */
class ShellHomeController
{
    public function __invoke(Request $request, ShellHome $home): RedirectResponse
    {
        $url = $home->url($request);

        abort_if($url === null, 404);

        return redirect($url);
    }
}
