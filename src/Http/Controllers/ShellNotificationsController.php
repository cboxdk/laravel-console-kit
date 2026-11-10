<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Cbox\Console\Kit\Contracts\NotificationSource;
use Cbox\Console\Kit\Shell\NotificationItem;
use Cbox\Console\Kit\Shell\ShellBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The bell: `GET /shell/notifications` → `{unread, data}`, `POST /shell/notifications/read`
 * `{id?}` → 204. 404 when the app has no notification source.
 */
class ShellNotificationsController
{
    public function __construct(private readonly ShellBuilder $shell) {}

    public function index(Request $request): JsonResponse
    {
        $source = $this->source();

        return response()->json([
            'unread' => $source->unread($request),
            'data' => array_map(
                static fn (NotificationItem $item): array => $item->toArray(),
                $source->latest($request, ShellInput::limit($request, 20, 50)),
            ),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request): Response
    {
        $this->source()->markRead($request, ShellInput::string($request, 'id'));

        return response()->noContent();
    }

    private function source(): NotificationSource
    {
        $source = $this->shell->notificationSource();

        abort_unless($source instanceof NotificationSource, 404);

        return $source;
    }
}
