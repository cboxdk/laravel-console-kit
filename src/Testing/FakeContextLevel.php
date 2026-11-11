<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Testing;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Contracts\CreatesContextItems;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextPage;
use Cbox\Console\Kit\Shell\ContextQuery;
use Cbox\Console\Kit\Shell\CreatedContextItem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Assert;

/**
 * An in-memory {@see ContextLevel} for tests, with "New …":
 *
 *     $orgs = FakeContextLevel::make('organization', 'Organization', 'org')
 *         ->with(new ContextItem('org_1', 'Cbox', 'cbox'));
 *     $projects = FakeContextLevel::make('project', 'Project', 'project', 'projects', parent: $orgs)
 *         ->with(new ContextItem('prj_1', 'Webshop', 'webshop'), parent: 'org_1');
 *
 * `current()` reads the level's route parameter (the slug) under the current parent.
 * URLs follow the URL standard by default: the top level is `/orgs/{slug}`, a deeper level
 * appends `/{segment}/{slug}` to its parent level's URL; `allUrl` is the level's collection, `settingsUrl` the item's
 * URL plus `/settings`. Override any of them with {@see urls()}.
 */
class FakeContextLevel implements ContextLevel, CreatesContextItems
{
    /** @var list<array{item: ContextItem, parent: string|null}> */
    private array $items = [];

    /** @var list<array{name: string, ancestors: list<string>}> */
    private array $created = [];

    private bool $creatable = true;

    /** @var (Closure(ContextItem, list<ContextItem>): string)|null */
    private ?Closure $url = null;

    /** @var (Closure(list<ContextItem>): string)|null */
    private ?Closure $allUrl = null;

    /** @var (Closure(Request, list<ContextItem>, self): ?ContextItem)|null */
    private ?Closure $current = null;

    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $routeParameter,
        private readonly string $segment = 'orgs',
        private readonly ?self $parent = null,
    ) {}

    /** A level whose URLs are `{parent's URL}/{segment}/{slug}` (`/orgs/{slug}` at the top). */
    public static function make(string $key, string $label, string $routeParameter, string $segment = 'orgs', ?self $parent = null): self
    {
        return new self($key, $label, $routeParameter, $segment, $parent);
    }

    /** Add an item, under the parent with this id (null for the top level). */
    public function with(ContextItem $item, ?string $parent = null): static
    {
        $this->items[] = ['item' => $item, 'parent' => $parent];

        return $this;
    }

    /**
     * @param  (Closure(ContextItem, list<ContextItem>): string)|null  $url
     * @param  (Closure(list<ContextItem>): string)|null  $allUrl
     */
    public function urls(?Closure $url = null, ?Closure $allUrl = null): static
    {
        $this->url = $url;
        $this->allUrl = $allUrl;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function routeParameter(): string
    {
        return $this->routeParameter;
    }

    /**
     * Resolve the current item another way than from this level's route parameter, as an app
     * does when a page's URL omits the level (the organization of `/projects/{project}`).
     * The resolver gets the request, the ancestors and this level; null restores the default.
     *
     * @param  (Closure(Request, list<ContextItem>, self): ?ContextItem)|null  $resolver
     */
    public function currentUsing(?Closure $resolver): static
    {
        $this->current = $resolver;

        return $this;
    }

    public function current(Request $request, array $ancestors): ?ContextItem
    {
        if ($this->current instanceof Closure) {
            return ($this->current)($request, $ancestors, $this);
        }

        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        $value = $route->originalParameter($this->routeParameter);

        if (! is_string($value)) {
            return null;
        }

        foreach ($this->under($ancestors) as $item) {
            if ($item->routeKey === $value) {
                return $item;
            }
        }

        return null;
    }

    public function find(Request $request, string $id, array $ancestors): ?ContextItem
    {
        foreach ($this->under($ancestors) as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    public function list(Request $request, ContextQuery $query): ContextPage
    {
        $items = $this->under($query->ancestors);

        if ($query->search !== null && $query->search !== '') {
            $needle = mb_strtolower($query->search);
            $items = array_values(array_filter(
                $items,
                static fn (ContextItem $item): bool => str_contains(mb_strtolower($item->name), $needle),
            ));
        }

        $offset = $query->cursor !== null && ctype_digit($query->cursor) ? (int) $query->cursor : 0;
        $page = array_slice($items, $offset, $query->limit);
        $next = $offset + $query->limit < count($items) ? (string) ($offset + $query->limit) : null;

        return new ContextPage($page, $next);
    }

    public function url(ContextItem $item, array $ancestors): string
    {
        if ($this->url instanceof Closure) {
            return ($this->url)($item, $ancestors);
        }

        return $this->base($ancestors).'/'.$this->segment.'/'.$item->routeKey;
    }

    public function allUrl(array $ancestors): string
    {
        if ($this->allUrl instanceof Closure) {
            return ($this->allUrl)($ancestors);
        }

        return $this->base($ancestors).'/'.$this->segment;
    }

    public function settingsUrl(ContextItem $item, array $ancestors): string
    {
        return $this->url($item, $ancestors).'/settings';
    }

    /** Whether "New …" is offered (default yes). */
    public function creatable(bool $creatable = true): static
    {
        $this->creatable = $creatable;

        return $this;
    }

    public function canCreate(Request $request, array $ancestors): bool
    {
        return $this->creatable;
    }

    public function create(Request $request, string $name, array $ancestors): CreatedContextItem
    {
        $parent = $ancestors === [] ? null : $ancestors[count($ancestors) - 1]->id;
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $item = new ContextItem($this->key.'_new_'.(count($this->created) + 1), $name, $slug === '' ? 'new' : $slug);

        $this->with($item, $parent);
        $this->created[] = ['name' => $name, 'ancestors' => array_map(static fn (ContextItem $a): string => $a->id, $ancestors)];

        return new CreatedContextItem($item->id, $this->url($item, $ancestors));
    }

    /**
     * What "New …" created, in order: the name and the ancestor ids.
     *
     * @return list<array{name: string, ancestors: list<string>}>
     */
    public function created(): array
    {
        return $this->created;
    }

    public function assertCreated(string $name): void
    {
        Assert::assertContains($name, array_column($this->created, 'name'), "Nothing named [{$name}] was created at the {$this->key} level.");
    }

    public function assertNothingCreated(): void
    {
        Assert::assertSame([], $this->created, "Something was created at the {$this->key} level.");
    }

    /**
     * @param  list<ContextItem>  $ancestors
     * @return list<ContextItem>
     */
    private function under(array $ancestors): array
    {
        $parent = $ancestors === [] ? null : $ancestors[count($ancestors) - 1]->id;
        $out = [];

        foreach ($this->items as $entry) {
            if ($entry['parent'] === $parent) {
                $out[] = $entry['item'];
            }
        }

        return $out;
    }

    /** @param list<ContextItem> $ancestors */
    private function base(array $ancestors): string
    {
        if ($ancestors === [] || ! $this->parent instanceof self) {
            return '';
        }

        $last = count($ancestors) - 1;

        return $this->parent->url($ancestors[$last], array_slice($ancestors, 0, $last));
    }
}
