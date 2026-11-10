<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Http\Controllers;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Contracts\CreatesContextItems;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextQuery;
use Cbox\Console\Kit\Shell\ShellBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A level's menu endpoints. The ancestors arrive as ids (`ancestors[organization]=…`,
 * with `parent` overriding the immediate parent, for the cascade's hovered project) and
 * are resolved through each level's `find()`: an id the person may not open is a 404.
 *
 *  - list      `GET  /shell/context/{level}?q=&parent=&cursor=&limit=` → `{object: "list", data, has_more, next_cursor}`
 *  - create    `POST /shell/context/{level}` `{name}` → `201 {id, detailsUrl}`
 *  - settings  `GET  /shell/context/{level}/{id}/settings` → redirect to the item's settings
 */
class ShellContextController
{
    public function __construct(private readonly ShellBuilder $shell) {}

    public function index(Request $request, string $level): JsonResponse
    {
        [$contextLevel, $ancestors] = $this->resolve($request, $level);

        $page = $contextLevel->list($request, new ContextQuery(
            ShellInput::string($request, 'q', 120),
            $ancestors,
            ShellInput::string($request, 'cursor', 512),
            ShellInput::limit($request, 25, 100),
        ));

        return response()->json($page->toArray())->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $level): JsonResponse
    {
        [$contextLevel, $ancestors] = $this->resolve($request, $level);

        abort_unless($contextLevel instanceof CreatesContextItems, 404);
        abort_unless($contextLevel->canCreate($request, $ancestors), 403);

        $request->validate(['name' => ['required', 'string', 'max:120']]);

        $created = $contextLevel->create($request, trim($request->string('name')->toString()), $ancestors);

        return response()->json($created->toArray(), 201);
    }

    public function settings(Request $request, string $level, string $id): RedirectResponse
    {
        [$contextLevel, $ancestors] = $this->resolve($request, $level);
        $item = $contextLevel->find($request, $id, $ancestors);

        abort_unless($item instanceof ContextItem, 404);

        return redirect($contextLevel->settingsUrl($item, $ancestors));
    }

    /** @return array{0: ContextLevel, 1: list<ContextItem>} */
    private function resolve(Request $request, string $key): array
    {
        $level = $this->shell->level($key);
        $index = $this->shell->levelIndex($key);

        abort_unless($level instanceof ContextLevel && $index !== null, 404);

        $ids = ShellInput::map($request, 'ancestors');
        $parent = ShellInput::string($request, 'parent');
        $parentLevel = $index > 0 ? $this->shell->contextLevels()[$index - 1] : null;

        if ($parent !== null && $parentLevel !== null) {
            $ids[$parentLevel->key()] = $parent;
        }

        $ancestors = $this->shell->ancestorsFromIds($request, $index, $ids);

        abort_if($ancestors === null, 404);

        return [$level, $ancestors];
    }
}
