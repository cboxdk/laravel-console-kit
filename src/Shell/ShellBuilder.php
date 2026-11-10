<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Contracts\CreatesContextItems;
use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Cbox\Console\Kit\Contracts\HelpConfig;
use Cbox\Console\Kit\Contracts\NavRegistry;
use Cbox\Console\Kit\Contracts\NotificationSource;
use Cbox\Console\Kit\Contracts\SearchProvider;
use Cbox\Console\Kit\Contracts\ShellPreferenceStore;
use Cbox\Console\Kit\Nav\NavArea;
use Cbox\Console\Kit\Nav\NavPage;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use LogicException;

/**
 * Describes the shell once, in a service provider, and builds the `shell` prop for each
 * request (spec §11.3):
 *
 *     Console::shell()
 *         ->app('infra', 'Cbox Infra')
 *         ->levels(new OrganizationLevel, new ProjectLevel, new EnvironmentLevel)
 *         ->search(new DeploymentSearch)
 *         ->actions(fn () => [PaletteAction::route('deployments.create', 'New deployment')])
 *         ->helpTopic(fn (Request $r) => $r->route()?->getName())
 *         ->notifications(null);
 *
 * Building reads only local state: the nav registry, the levels (the app's own data),
 * cookies and config. Lists, search, notifications and Help load in the browser after
 * first paint, from the routes `Console::shellRoutes()` registers.
 */
class ShellBuilder
{
    /** Route names of the endpoints {@see ShellRoutes} registers. */
    public const ROUTE_SWITCH = 'console.shell.switch';

    public const ROUTE_LIST = 'console.shell.context.list';

    public const ROUTE_CREATE = 'console.shell.context.create';

    public const ROUTE_SETTINGS = 'console.shell.context.settings';

    public const ROUTE_SEARCH = 'console.shell.search';

    public const ROUTE_NOTIFICATIONS = 'console.shell.notifications';

    public const ROUTE_PREFERENCES = 'console.shell.preferences';

    public const ROUTE_HOME = 'console.shell.home';

    private ?ShellApp $app = null;

    /** @var (Closure(Request): ?ShellPerson)|null */
    private ?Closure $person = null;

    /** @var list<ContextLevel> */
    private array $levels = [];

    private ?SearchProvider $search = null;

    /** @var (Closure(Request): list<PaletteAction>)|list<PaletteAction> */
    private Closure|array $actions = [];

    private ?HelpConfig $help = null;

    /** @var (Closure(Request): ?string)|null */
    private ?Closure $helpTopic = null;

    private ?NotificationSource $notifications = null;

    /** @var (Closure(Request): ?ShellBanner)|null */
    private ?Closure $banner = null;

    /** @var string|(Closure(ContextItem): string)|null */
    private string|Closure|null $sandboxText = null;

    private ?string $catalogUrl = null;

    /** @var (Closure(Route): ?string)|null */
    private ?Closure $listFallback = null;

    public function __construct(
        private readonly NavRegistry $nav,
        private readonly FeatureRegistry $features,
        private readonly Router $router,
        private readonly ShellPreferenceStore $preferences,
        private readonly Gate $gate,
    ) {}

    /** The app's product key (`id`, `billing`, `infra`…), its name and where the brand row links. */
    public function app(string $key, string $name, string $homeUrl = '/'): static
    {
        $this->app = new ShellApp($key, $name, $homeUrl);

        return $this;
    }

    /**
     * Who the person is. Default: the authenticated user's id, `name` (or `email`) and
     * `avatar_url`.
     *
     * @param  Closure(Request): ?ShellPerson  $resolver
     */
    public function person(Closure $resolver): static
    {
        $this->person = $resolver;

        return $this;
    }

    /** The context levels, outermost first. One to three (spec §5.1). */
    public function levels(ContextLevel ...$levels): static
    {
        $levels = array_values($levels);

        if (count($levels) > 3) {
            throw new InvalidArgumentException('A shell has at most three context levels.');
        }

        $keys = array_map(static fn (ContextLevel $level): string => $level->key(), $levels);

        if (count(array_unique($keys)) !== count($keys)) {
            throw new InvalidArgumentException('Context level keys must be unique.');
        }

        $this->levels = $levels;

        return $this;
    }

    public function search(?SearchProvider $provider): static
    {
        $this->search = $provider;

        return $this;
    }

    /**
     * The palette's actions, or a resolver evaluated per request.
     *
     * @param  (Closure(Request): list<PaletteAction>)|list<PaletteAction>  $actions
     */
    public function actions(Closure|array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function help(?HelpConfig $help): static
    {
        $this->help = $help;

        return $this;
    }

    /**
     * The page's help topic key (spec §7.2), e.g. the route name.
     *
     * @param  (Closure(Request): ?string)|null  $resolver
     */
    public function helpTopic(?Closure $resolver): static
    {
        $this->helpTopic = $resolver;

        return $this;
    }

    /** The bell's source. Null (the default) means no bell at all. */
    public function notifications(?NotificationSource $source): static
    {
        $this->notifications = $source;

        return $this;
    }

    /**
     * An app banner (impersonation, a notice). It wins over the sandbox strip.
     *
     * @param  (Closure(Request): ?ShellBanner)|null  $resolver
     */
    public function banner(?Closure $resolver): static
    {
        $this->banner = $resolver;

        return $this;
    }

    /**
     * The sandbox strip's text when the current environment has `testData: true`.
     *
     * @param  string|(Closure(ContextItem): string)  $text
     */
    public function sandboxText(string|Closure $text): static
    {
        $this->sandboxText = $text;

        return $this;
    }

    /** Where the app switcher reads the catalog. Default: laravel-id-client's `cbox-id.apps.catalog`. */
    public function appCatalogUrl(string $url): static
    {
        $this->catalogUrl = $url;

        return $this;
    }

    /**
     * The list page an entity page falls back to on a switch (spec §5.6), as a route
     * name. Default: the nav page in the same resource (`deployments.show` →
     * `deployments.index`).
     *
     * @param  Closure(Route): ?string  $resolver
     */
    public function listFallback(Closure $resolver): static
    {
        $this->listFallback = $resolver;

        return $this;
    }

    /** @return list<ContextLevel> */
    public function contextLevels(): array
    {
        return $this->levels;
    }

    public function searchProvider(): ?SearchProvider
    {
        return $this->search;
    }

    public function notificationSource(): ?NotificationSource
    {
        return $this->notifications;
    }

    public function preferenceStore(): ShellPreferenceStore
    {
        return $this->preferences;
    }

    /** The level with this key and its position, or null. */
    public function level(string $key): ?ContextLevel
    {
        foreach ($this->levels as $level) {
            if ($level->key() === $key) {
                return $level;
            }
        }

        return null;
    }

    public function levelIndex(string $key): ?int
    {
        foreach ($this->levels as $index => $level) {
            if ($level->key() === $key) {
                return $index;
            }
        }

        return null;
    }

    /** The context the request is in: each level's current item, until one has none. */
    public function resolveContext(Request $request): ResolvedContext
    {
        $levels = [];
        $items = [];

        foreach ($this->levels as $level) {
            $item = $level->current($request, $items);

            if (! $item instanceof ContextItem) {
                break;
            }

            $levels[] = $level;
            $items[] = $item;
        }

        return new ResolvedContext($levels, $items);
    }

    /**
     * The ancestors of level `$index`, resolved from ids through each level's `find()`.
     * Null when one is missing or the person may not open it (deny by default).
     *
     * @param  array<string, string>  $ids  level key => id
     * @return list<ContextItem>|null
     */
    public function ancestorsFromIds(Request $request, int $index, array $ids): ?array
    {
        $ancestors = [];

        foreach (array_slice($this->levels, 0, $index) as $level) {
            $id = $ids[$level->key()] ?? null;
            $item = is_string($id) && $id !== '' ? $level->find($request, $id, $ancestors) : null;

            if (! $item instanceof ContextItem) {
                return null;
            }

            $ancestors[] = $item;
        }

        return $ancestors;
    }

    /** The list page an entity page falls back to, as a route name, or null. */
    public function fallbackRouteFor(Route $route): ?string
    {
        if ($this->listFallback instanceof Closure) {
            $name = ($this->listFallback)($route);

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $name = $route->getName();

        if ($name === null || ! str_contains($name, '.')) {
            return null;
        }

        $resource = substr($name, 0, (int) strrpos($name, '.') + 1);

        // The resource's `.index` route is its list; else the first nav page of the resource.
        if ($resource.'index' !== $name && $this->router->has($resource.'index')) {
            return $resource.'index';
        }

        foreach ($this->nav->areas() as $area) {
            foreach ($area->pages() as $page) {
                if ($page->route !== $name && str_starts_with($page->route, $resource)) {
                    return $page->route;
                }
            }
        }

        return null;
    }

    /**
     * The `shell` prop for this request.
     *
     * @throws LogicException when `Console::shellRoutes()` was not registered
     */
    public function build(Request $request): Shell
    {
        if (! $this->router->has(self::ROUTE_SWITCH)) {
            throw new LogicException('The shell routes are not registered. Call Console::shellRoutes() in routes/web.php.');
        }

        $context = $this->resolveContext($request);
        $current = $request->route();
        $current = $current instanceof Route ? $current : null;

        return new Shell(
            app: $this->resolveApp(),
            person: $this->resolvePerson($request),
            nav: $this->buildNav($request, $current, $context),
            context: new ShellContext($this->buildLevels($request, $context), route(self::ROUTE_SWITCH, [], false)),
            palette: new ShellPalette($this->searchUrl($request, $context), $this->buildActions($request, $current)),
            help: $this->buildHelp($request),
            notifications: $this->notifications instanceof NotificationSource
                ? ShellNotifications::enabled($this->notifications->unread($request), route(self::ROUTE_NOTIFICATIONS, [], false))
                : ShellNotifications::disabled(),
            apps: new ShellApps($this->catalogUrl ?? ($this->router->has('cbox-id.apps.catalog') ? route('cbox-id.apps.catalog', [], false) : '/apps/catalog')),
            banner: $this->buildBanner($request, $context),
            preferences: $this->preferences->read($request),
        );
    }

    private function resolveApp(): ShellApp
    {
        if ($this->app instanceof ShellApp) {
            return $this->app;
        }

        $name = config('app.name');

        return new ShellApp('app', is_string($name) && $name !== '' ? $name : 'App', '/');
    }

    private function resolvePerson(Request $request): ShellPerson
    {
        if ($this->person instanceof Closure) {
            return ($this->person)($request) ?? new ShellPerson;
        }

        $user = $request->user();

        if (! $user instanceof Authenticatable) {
            return new ShellPerson;
        }

        $id = $user->getAuthIdentifier();
        $name = self::attribute($user, 'name') ?? self::attribute($user, 'email') ?? '';

        return new ShellPerson(
            is_scalar($id) ? (string) $id : '',
            $name,
            null,
            self::attribute($user, 'avatar_url'),
        );
    }

    private static function attribute(Authenticatable $user, string $key): ?string
    {
        // data_get reads an Eloquent attribute, a GenericUser's and a plain property alike.
        $value = data_get($user, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function buildNav(Request $request, ?Route $current, ResolvedContext $context): ShellNav
    {
        $gate = $this->gate->forUser($request->user());
        $currentName = $current?->getName();
        $areas = [];
        $activeArea = '';
        $activePage = null;
        $prefixArea = '';
        $prefixPage = null;
        $resource = $currentName !== null && str_contains($currentName, '.')
            ? substr($currentName, 0, (int) strrpos($currentName, '.') + 1) : null;

        foreach ($this->nav->areas() as $area) {
            if (! $this->visible($area, $gate, $request, $context)) {
                continue;
            }

            $pages = [];

            foreach ($area->pages() as $page) {
                if (! $this->visible($page, $gate, $request, $context)) {
                    continue;
                }

                $href = ShellUrls::route($this->router, $page->route, $current);

                if ($href === null) {
                    continue;
                }

                $pages[] = new ShellNavPage($page->route, $page->label, $href);

                if ($page->route === $currentName && $activeArea === '') {
                    $activeArea = $area->key;
                    $activePage = $page->route;
                } elseif ($resource !== null && $prefixArea === '' && str_starts_with($page->route, $resource)) {
                    $prefixArea = $area->key;
                    $prefixPage = $page->route;
                }
            }

            if ($pages === []) {
                continue;
            }

            $areas[] = new ShellNavArea($area->key, $area->label, $pages[0]->href, $pages, $area->icon);
        }

        if ($activeArea === '' && $prefixArea !== '') {
            $activeArea = $prefixArea;
            $activePage = $prefixPage;
        }

        return new ShellNav($areas, $activeArea, $activePage);
    }

    /**
     * Whether a nav area or page shows: its feature is active and the person may use its
     * ability, checked with the arguments it declares (deny by default when the check fails).
     */
    private function visible(NavArea|NavPage $entry, Gate $gate, Request $request, ResolvedContext $context): bool
    {
        if ($entry->feature !== null && ! $this->features->active($entry->feature)) {
            return false;
        }

        if ($entry->ability === null) {
            return true;
        }

        $arguments = $entry->abilityArguments instanceof Closure
            ? ($entry->abilityArguments)($request, $context->items)
            : $entry->abilityArguments;

        return $gate->allows($entry->ability, $arguments);
    }

    /** @return list<ShellContextLevel> */
    private function buildLevels(Request $request, ResolvedContext $context): array
    {
        $out = [];

        foreach ($context->levels as $index => $level) {
            $ancestors = array_slice($context->items, 0, $index);
            $ancestorIds = [];

            foreach ($ancestors as $i => $ancestor) {
                $ancestorIds[$context->levels[$i]->key()] = $ancestor->id;
            }

            $query = $ancestorIds === [] ? [] : ['ancestors' => $ancestorIds];
            $settings = route(self::ROUTE_SETTINGS, ['level' => $level->key(), 'id' => '__ID__', ...$query], false);

            $out[] = new ShellContextLevel(
                key: $level->key(),
                label: $level->label(),
                current: $context->items[$index],
                listUrl: route(self::ROUTE_LIST, ['level' => $level->key(), ...$query], false),
                allUrl: $level->allUrl($ancestors),
                settingsUrl: str_replace('__ID__', ShellContextLevel::SETTINGS_PLACEHOLDER, $settings),
                createUrl: $level instanceof CreatesContextItems && $level->canCreate($request, $ancestors)
                    ? route(self::ROUTE_CREATE, ['level' => $level->key(), ...$query], false)
                    : null,
            );
        }

        return $out;
    }

    private function searchUrl(Request $request, ResolvedContext $context): string
    {
        $from = ShellUrls::samePath($request->getRequestUri()) ?? '/';
        $ids = $context->ids();

        return route(self::ROUTE_SEARCH, array_filter(['context' => $ids === [] ? null : $ids, 'from' => $from]), false);
    }

    /** @return list<PaletteAction> */
    private function buildActions(Request $request, ?Route $current): array
    {
        $actions = $this->actions instanceof Closure ? ($this->actions)($request) : $this->actions;
        $out = [];

        foreach ($actions as $action) {
            $resolved = $action->resolve($this->router, $current);

            if ($resolved instanceof PaletteAction) {
                $out[] = $resolved;
            }
        }

        return $out;
    }

    private function buildHelp(Request $request): ShellHelp
    {
        $help = $this->help instanceof HelpConfig ? $this->help->resolve($request) : new ShellHelp;
        $topic = $this->helpTopic instanceof Closure ? ($this->helpTopic)($request) : null;

        if ($topic === null || $topic === '') {
            return $help;
        }

        return new ShellHelp($help->enabled, $topic, $help->sessionUrl, $help->portalUrl, $help->updatesUrl);
    }

    private function buildBanner(Request $request, ResolvedContext $context): ?ShellBanner
    {
        $banner = $this->banner instanceof Closure ? ($this->banner)($request) : null;

        if ($banner instanceof ShellBanner) {
            return $banner;
        }

        $deepest = $context->deepest();

        if (! $deepest instanceof ContextItem || $deepest->testData !== true) {
            return null;
        }

        $text = $this->sandboxText instanceof Closure
            ? ($this->sandboxText)($deepest)
            : ($this->sandboxText ?? __('You are in :name. Everything here is test data.', ['name' => $deepest->name]));

        return new ShellBanner(BannerKind::Sandbox, is_string($text) ? $text : '');
    }
}
