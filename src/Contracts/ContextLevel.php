<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextPage;
use Cbox\Console\Kit\Shell\ContextQuery;
use Illuminate\Http\Request;

/**
 * One level of the header's context selectors: Organization, then the app's second level
 * (Project, Site, Space…), then Environment (spec §5). An app declares one to three, in
 * order, with `Console::shell()->levels(...)`.
 *
 * LOCAL FIRST: every method answers from the app's own data (its tables, its mirror of
 * Cbox ID). None may call another service (spec §11.2a).
 *
 * DENY BY DEFAULT: {@see find()} and {@see list()} only ever return items the request's
 * person may open, under the given ancestors. The kit trusts what they return: an id a
 * client posts is resolved through {@see find()} and refused when it answers null.
 *
 * Add {@see CreatesContextItems} to offer "New …" in the menu.
 */
interface ContextLevel
{
    /** `organization`, `project`, `environment`, or the app's own key. Unique per shell. */
    public function key(): string;

    /** What the level is called in the UI: "Organization", "Project", "Site", "Space"… */
    public function label(): string;

    /** The route parameter that carries this level in the app's URLs (`org`, `project`). */
    public function routeParameter(): string;

    /**
     * The item the request is in, or null when the page is not inside this level (then
     * neither this level nor any deeper one is shown).
     *
     * @param  list<ContextItem>  $ancestors  the resolved outer levels, outermost first
     */
    public function current(Request $request, array $ancestors): ?ContextItem;

    /**
     * The item with this id under these ancestors, if the person may open it.
     *
     * @param  list<ContextItem>  $ancestors
     */
    public function find(Request $request, string $id, array $ancestors): ?ContextItem;

    /** One page of the person's items under the query's ancestors, searchable by name. */
    public function list(Request $request, ContextQuery $query): ContextPage;

    /**
     * The item's own start page, where a switch lands when the current page has no
     * equivalent there (`/orgs/cbox`, `/orgs/cbox/projects/webshop`).
     *
     * @param  list<ContextItem>  $ancestors
     */
    public function url(ContextItem $item, array $ancestors): string;

    /**
     * "View all" in the menu (`/orgs`, `/orgs/cbox/projects`).
     *
     * @param  list<ContextItem>  $ancestors
     */
    public function allUrl(array $ancestors): string;

    /**
     * The gear on a row: the item's settings page.
     *
     * @param  list<ContextItem>  $ancestors
     */
    public function settingsUrl(ContextItem $item, array $ancestors): string;
}
