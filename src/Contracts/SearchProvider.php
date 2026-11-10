<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Contracts;

use Cbox\Console\Kit\Shell\SearchQuery;
use Cbox\Console\Kit\Shell\SearchResult;
use Illuminate\Http\Request;

/**
 * The app's records for the palette's "Records" group (spec §6.1): deployments,
 * invoices, members… in the current context, from the app's own data.
 */
interface SearchProvider
{
    /**
     * At most `$query->limit` results, best first.
     *
     * @return list<SearchResult>
     */
    public function search(Request $request, SearchQuery $query): array;
}
