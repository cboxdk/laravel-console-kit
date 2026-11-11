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
use Throwable;

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

    /** @var list<Closure(Request): (ShellBanner|list<ShellBanner>|null)> */
    private array $banners = [];

    /** @var string|(Closure(ContextItem): string)|null */
    private string|Closure|null $sandboxText = null;

    private ?string $catalogUrl = null;

    /** @var (Closure(Route): ?string)|null */
    private ?Closure $listFallback = null;

    /** @var (Closure(NavPage, Request, list<ContextItem>): bool)|null */
    private ?Closure $navFilter = null;

    /** @var string|(Closure(Request): ?string)|null */
    private string|Closure|null $palettePlaceholder = null;

    /** @var array<string, mixed>|null */
    private ?array $account = null;

    /** Route names the account menu uses when the app does not say (laravel-id-client conventions). */
    public const DEFAULT_PROFILE_ROUTE = 'profile';

    public const DEFAULT_SWITCH_ACCOUNT_ROUTE = 'login.switch';

    public const DEFAULT_SIGN_OUT_ROUTE = 'logout';

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
     * The app's banner (impersonation, a notice such as a tax hold), replacing any set before.
     * The resolver returns one banner, a list of them, or null. App banners never hide the
     * sandbox strip: `shell.banners` carries both. `shell.banner`, which cbox-ui 0.10.1 and
     * older read, keeps the first app banner, else the strip.
     *
     * @param  (Closure(Request): (ShellBanner|list<ShellBanner>|null))|null  $resolver
     */
    public function banner(?Closure $resolver): static
    {
        $this->banners = $resolver instanceof Closure ? [$resolver] : [];

        return $this;
    }

    /**
     * One more banner resolver, after those already set, so a plugin can add its own without
     * replacing the app's. Same return shape as {@see banner()}.
     *
     * @param  Closure(Request): (ShellBanner|list<ShellBanner>|null)  $resolver
     */
    public function addBanner(Closure $resolver): static
    {
        $this->banners[] = $resolver;

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
     * The list page an entity page falls back to on a switch (spec §5.6), and the nav page
     * a record page lights, as a route name. Default: the resource's `.index` route on a
     * switch, then the nearest parent route that is a nav page (`billing.coupons.show` →
     * `billing.coupons`), then the nav page sharing the most whole name segments.
     *
     * @param  Closure(Route): ?string  $resolver
     */
    public function listFallback(Closure $resolver): static
    {
        $this->listFallback = $resolver;

        return $this;
    }

    /**
     * Which nav pages this person sees, decided per request: return false to leave a page
     * out of the rail, the subnav, the phone nav and the palette. It runs after the page's
     * own feature and `can:` checks, with the context the request is in (outermost first),
     * so an app can reuse its route access check:
     *
     *     ->navFilter(fn (NavPage $page, Request $request) => app(RouteAccess::class)->allows($page->route))
     *
     * @param  (Closure(NavPage, Request, list<ContextItem>): bool)|null  $filter
     */
    public function navFilter(?Closure $filter): static
    {
        $this->navFilter = $filter;

        return $this;
    }

    /**
     * The palette search field's placeholder, e.g. "Search customers, invoices… or pages".
     * A string, or a resolver per request. Default (null): cbox-ui's "Search or ask…".
     *
     * @param  string|(Closure(Request): ?string)|null  $placeholder
     */
    public function palettePlaceholder(string|Closure|null $placeholder): static
    {
        $this->palettePlaceholder = $placeholder;

        return $this;
    }

    /**
     * The account menu's links (spec §10). Each URL is a route name (built with the current
     * page's parameters), a `/path` or `https://` URL, or a closure over the request that
     * returns one; null keeps the default, false hides the row.
     *
     * Defaults when not given:
     *  - profile: laravel-id-client's `IdentityClient::profileUrl()` (Cbox ID's Profile and
     *    security page, built from config, no request), else the `profile` route;
     *  - switch account: the `login.switch` route;
     *  - sign out: the `logout` route, as a POST.
     *
     * A default that does not resolve is left out, and so is `account` when nothing does.
     *
     * @param  string|false|(Closure(Request): ?string)|null  $profileUrl
     * @param  string|false|(Closure(Request): ?string)|null  $switchAccountUrl
     * @param  string|false|(Closure(Request): ?string)|null  $signOutUrl
     *                                                                     `signedInWith` is the line under the name ("Signed in with Cbox ID · Production EU"): a
     *                                                                     string, or a closure over the request for what only the session knows.
     * @param  'get'|'post'  $signOutMethod
     * @param  string|(Closure(Request): ?string)|null  $signedInWith
     * @param  array<string, string>  $languages  code => label
     * @param  array<string, string>  $formats  code => label
     */
    public function account(
        string|false|Closure|null $profileUrl = null,
        string|false|Closure|null $switchAccountUrl = null,
        string|false|Closure|null $signOutUrl = null,
        string $signOutMethod = 'post',
        string|Closure|null $signedInWith = null,
        array $languages = [],
        array $formats = [],
    ): static {
        $this->account = compact('profileUrl', 'switchAccountUrl', 'signOutUrl', 'signOutMethod', 'signedInWith', 'languages', 'formats');

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

        // The resource's `.index` route is its list; else the nearest nav page by whole segments.
        if ($resource.'index' !== $name && $this->router->has($resource.'index')) {
            return $resource.'index';
        }

        $pages = [];

        foreach ($this->nav->areas() as $area) {
            foreach ($area->pages() as $page) {
                if ($page->route !== $name) {
                    $pages[] = $page->route;
                }
            }
        }

        return self::nearestNavPage($name, $pages);
    }

    /**
     * The nav page nearest to route `$name` among `$pages` (route names, in nav order): the
     * nearest parent route that is one (`billing.subscriptions.show` → `billing.subscriptions`),
     * else the page sharing the most whole leading segments with it. Names are compared by
     * segment, never by string prefix, so `billing.subscriptions.show` never lights
     * `billing.subscriptions.dunning` while `billing.subscriptions` is a page, and `billing.sub`
     * never matches `billing.subscriptions`.
     *
     * @param  list<string>  $pages
     */
    private static function nearestNavPage(string $name, array $pages): ?string
    {
        foreach (self::parentRoutes($name) as $parent) {
            if (in_array($parent, $pages, true)) {
                return $parent;
            }
        }

        return self::longestSegmentMatch($name, $pages);
    }

    /**
     * The page sharing the most whole leading segments with `$name`. It must share at least
     * two (one for a two-segment name, `coupons.show` → `coupons.index`), so an app's name
     * prefix alone (`billing`) never lights a page. On a tie: the resource's `.index` page,
     * then the page with the fewest segments, then nav order.
     *
     * @param  list<string>  $pages
     */
    private static function longestSegmentMatch(string $name, array $pages): ?string
    {
        $segments = explode('.', $name);
        $minimum = min(2, count($segments) - 1);

        if ($minimum < 1) {
            return null;
        }

        $best = null;
        $bestRank = null;

        foreach ($pages as $order => $page) {
            if ($page === $name) {
                continue;
            }

            $candidate = explode('.', $page);
            $shared = 0;

            while (isset($segments[$shared], $candidate[$shared]) && $segments[$shared] === $candidate[$shared]) {
                $shared++;
            }

            if ($shared < $minimum) {
                continue;
            }

            $isIndex = count($candidate) === $shared + 1 && $candidate[$shared] === 'index';
            $rank = [-$shared, $isIndex ? 0 : 1, count($candidate), $order];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $page;
                $bestRank = $rank;
            }
        }

        return $best;
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
        $banners = $this->buildBanners($request, $context);

        return new Shell(
            app: $this->resolveApp(),
            person: $this->resolvePerson($request),
            nav: $this->buildNav($request, $current, $context),
            context: new ShellContext($this->buildLevels($request, $context), route(self::ROUTE_SWITCH, [], false)),
            palette: new ShellPalette($this->searchUrl($request, $context), $this->buildActions($request, $current), $this->resolvePlaceholder($request)),
            help: $this->buildHelp($request),
            notifications: $this->notifications instanceof NotificationSource
                ? ShellNotifications::enabled($this->notifications->unread($request), route(self::ROUTE_NOTIFICATIONS, [], false))
                : ShellNotifications::disabled(),
            // The switcher shows the apps the organization does not have yet too (dimmed), so it
            // asks laravel-id-client's catalog for them; older switchers do not.
            apps: new ShellApps($this->catalogUrl ?? ($this->router->has('cbox-id.apps.catalog')
                ? route('cbox-id.apps.catalog', ['include' => 'available'], false)
                : '/apps/catalog?include=available')),
            banner: $banners['banner'],
            preferences: $this->preferences->read($request),
            account: $this->buildAccount($request, $current),
            banners: $banners['banners'],
        );
    }

    private function buildAccount(Request $request, ?Route $current): ShellAccount
    {
        $config = $this->account ?? [];
        $method = ($config['signOutMethod'] ?? 'post') === 'get' ? 'get' : 'post';
        $signedInWith = $config['signedInWith'] ?? null;
        $signedInWith = $signedInWith instanceof Closure ? $signedInWith($request) : $signedInWith;
        $languages = $config['languages'] ?? [];
        $formats = $config['formats'] ?? [];

        return new ShellAccount(
            profileUrl: $this->accountUrl($config['profileUrl'] ?? null, $request, $current, fn (): ?string => $this->cboxIdProfileUrl() ?? $this->routeUrl(self::DEFAULT_PROFILE_ROUTE, $current)),
            switchAccountUrl: $this->accountUrl($config['switchAccountUrl'] ?? null, $request, $current, fn (): ?string => $this->routeUrl(self::DEFAULT_SWITCH_ACCOUNT_ROUTE, $current)),
            signOutUrl: $this->accountUrl($config['signOutUrl'] ?? null, $request, $current, fn (): ?string => $this->routeUrl(self::DEFAULT_SIGN_OUT_ROUTE, $current)),
            signOutMethod: $method,
            signedInWith: is_string($signedInWith) && $signedInWith !== '' ? $signedInWith : null,
            languages: is_array($languages) ? self::stringMap($languages) : [],
            formats: is_array($formats) ? self::stringMap($formats) : [],
        );
    }

    /**
     * @param  Closure(): ?string  $default
     */
    private function accountUrl(mixed $target, Request $request, ?Route $current, Closure $default): ?string
    {
        if ($target === false) {
            return null;
        }

        if ($target instanceof Closure) {
            $target = $target($request);
        }

        if (! is_string($target) || $target === '') {
            return $default();
        }

        if ($this->router->has($target)) {
            return $this->routeUrl($target, $current);
        }

        return ShellUrls::samePath($target) ?? (str_starts_with($target, 'https://') ? $target : null);
    }

    private function routeUrl(string $name, ?Route $current): ?string
    {
        return $this->router->has($name) ? ShellUrls::route($this->router, $name, $current) : null;
    }

    /** Cbox ID's Profile and security page, when laravel-id-client is installed and configured. */
    private function cboxIdProfileUrl(): ?string
    {
        $class = 'Cbox\\Id\\Client\\IdentityClient';

        if (! class_exists($class) || ! app()->bound($class)) {
            return null;
        }

        try {
            $client = app($class);
            $url = is_object($client) && method_exists($client, 'profileUrl') ? $client->profileUrl() : null;
        } catch (Throwable) {
            return null;
        }

        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * @param  array<mixed>  $map
     * @return array<string, string>
     */
    private static function stringMap(array $map): array
    {
        $out = [];

        foreach ($map as $code => $label) {
            if (is_string($label) && $label !== '') {
                $out[(string) $code] = $label;
            }
        }

        return $out;
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

        foreach ($this->nav->areas() as $area) {
            if (! $this->featureOn($area) || ! $this->allowed($area, $gate, $request, $context)) {
                continue;
            }

            $pages = [];

            foreach ($area->pages() as $page) {
                $locked = ! $this->featureOn($page);

                if ($locked && ! $page->lockedWhenOff) {
                    continue;
                }

                if (! $this->allowed($page, $gate, $request, $context)) {
                    continue;
                }

                if ($this->navFilter instanceof Closure && ! ($this->navFilter)($page, $request, $context->items)) {
                    continue;
                }

                $href = $locked
                    ? $this->upgradeHref($page, $current) ?? ShellUrls::route($this->router, $page->route, $current)
                    : ShellUrls::route($this->router, $page->route, $current);

                if ($href === null) {
                    continue;
                }

                $pages[] = new ShellNavPage(
                    $page->route,
                    $page->label,
                    $href,
                    $locked,
                    $locked ? null : $this->countFor($page, $request, $context),
                    $page->group,
                );

                if ($page->route === $currentName && $activeArea === '') {
                    $activeArea = $area->key;
                    $activePage = $page->route;
                }
            }

            if ($pages === []) {
                continue;
            }

            $areas[] = new ShellNavArea($area->key, $area->label, $pages[0]->href, self::grouped($pages), $area->icon);
        }

        // A record page (`subscriptions.show`) is not a nav page itself: light its list page
        // and area. The app's list fallback wins, then the nearest parent route that is a nav
        // page (`subscriptions`), then the page sharing the most whole segments (a sibling such
        // as `subscriptions.index`). Never a bare string prefix: `subscriptions.dunning` is
        // not the list of `subscriptions.show`.
        if ($activeArea === '' && $current instanceof Route && $currentName !== null) {
            $fallback = $this->listFallback instanceof Closure ? ($this->listFallback)($current) : null;
            $found = is_string($fallback) && $fallback !== '' ? self::findPage($areas, [$fallback]) : null;

            if ($found === null) {
                $pages = [];

                foreach ($areas as $area) {
                    foreach ($area->pages as $page) {
                        $pages[] = $page->key;
                    }
                }

                $nearest = self::nearestNavPage($currentName, $pages);
                $found = $nearest !== null ? self::findPage($areas, [$nearest]) : null;
            }

            [$activeArea, $activePage] = $found ?? ['', null];
        }

        return new ShellNav($areas, $activeArea, $activePage);
    }

    /**
     * The first of `$names` that is a page in `$areas`, as [area key, page key].
     *
     * @param  list<ShellNavArea>  $areas
     * @param  list<string>  $names
     * @return array{0: string, 1: string}|null
     */
    private static function findPage(array $areas, array $names): ?array
    {
        foreach ($names as $name) {
            foreach ($areas as $area) {
                foreach ($area->pages as $page) {
                    if ($page->key === $name) {
                        return [$area->key, $page->key];
                    }
                }
            }
        }

        return null;
    }

    /**
     * The parents of a route name, nearest first: `billing.coupons.lines.show` gives
     * `billing.coupons.lines` and `billing.coupons`. The top segment alone is only a parent
     * of a two-segment name (`coupons.show` gives `coupons`), so an app's name prefix
     * (`billing`) never lights a page for every route under it.
     *
     * @return list<string>
     */
    private static function parentRoutes(string $name): array
    {
        $parents = [];

        while (str_contains($name, '.')) {
            $name = substr($name, 0, (int) strrpos($name, '.'));

            if ($parents !== [] && ! str_contains($name, '.')) {
                break;
            }

            $parents[] = $name;
        }

        return $parents;
    }

    /**
     * Pages of one group together, in the order each group first appears; pages without a
     * group keep their place among them.
     *
     * @param  list<ShellNavPage>  $pages
     * @return list<ShellNavPage>
     */
    private static function grouped(array $pages): array
    {
        $buckets = [];

        foreach ($pages as $page) {
            $buckets[$page->group ?? ''][] = $page;
        }

        return array_merge(...array_values($buckets));
    }

    private function upgradeHref(NavPage $page, ?Route $current): ?string
    {
        $target = $page->upgradeUrl;

        if ($target === null || $target === '') {
            return null;
        }

        if ($this->router->has($target)) {
            return ShellUrls::route($this->router, $target, $current);
        }

        return ShellUrls::samePath($target) ?? (str_starts_with($target, 'https://') ? $target : null);
    }

    private function countFor(NavPage $page, Request $request, ResolvedContext $context): ?int
    {
        $count = $page->count instanceof Closure ? ($page->count)($request, $context->items) : $page->count;

        return is_int($count) && $count > 0 ? $count : null;
    }

    private function featureOn(NavArea|NavPage $entry): bool
    {
        return $entry->feature === null || $this->features->active($entry->feature);
    }

    /**
     * Whether the person may use a nav area's or page's ability, checked with the arguments
     * it declares (deny by default when the check fails).
     */
    private function allowed(NavArea|NavPage $entry, Gate $gate, Request $request, ResolvedContext $context): bool
    {
        if ($entry->ability === null) {
            return true;
        }

        $arguments = $entry->abilityArguments instanceof Closure
            ? ($entry->abilityArguments)($request, $context->items)
            : $entry->abilityArguments;

        return $gate->allows($entry->ability, $arguments);
    }

    private function resolvePlaceholder(Request $request): ?string
    {
        $placeholder = $this->palettePlaceholder instanceof Closure
            ? ($this->palettePlaceholder)($request)
            : $this->palettePlaceholder;

        return is_string($placeholder) && $placeholder !== '' ? $placeholder : null;
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

    /**
     * The app's banners and the sandbox strip. `banners` holds the strip first (it sits under
     * the header, spec §4.3) and then every app banner, in the order the resolvers were added.
     * `banner` is the single strip older cbox-ui reads: the first app banner, else the strip.
     *
     * @return array{banner: ?ShellBanner, banners: list<ShellBanner>}
     */
    private function buildBanners(Request $request, ResolvedContext $context): array
    {
        $app = [];

        foreach ($this->banners as $resolver) {
            $result = $resolver($request);

            foreach (is_array($result) ? $result : [$result] as $banner) {
                if ($banner instanceof ShellBanner) {
                    $app[] = $banner;
                }
            }
        }

        $appSandbox = array_filter($app, static fn (ShellBanner $banner): bool => $banner->kind === BannerKind::Sandbox) !== [];
        $sandbox = $appSandbox ? null : $this->sandboxStrip($context);
        $banners = $sandbox instanceof ShellBanner ? [$sandbox, ...$app] : $app;

        return ['banner' => $app[0] ?? $sandbox, 'banners' => $banners];
    }

    private function sandboxStrip(ResolvedContext $context): ?ShellBanner
    {
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
