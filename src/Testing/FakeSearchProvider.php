<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Testing;

use Cbox\Console\Kit\Contracts\SearchProvider;
use Cbox\Console\Kit\Shell\SearchQuery;
use Cbox\Console\Kit\Shell\SearchResult;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;

/**
 * An in-memory {@see SearchProvider}: records whose title contains the term, and every
 * query it was asked, so a test can assert the palette searched in the right context.
 */
class FakeSearchProvider implements SearchProvider
{
    /** @var list<SearchResult> */
    private array $records = [];

    /** @var list<SearchQuery> */
    private array $queries = [];

    private ?\Throwable $failWith = null;

    public function with(SearchResult ...$records): static
    {
        foreach ($records as $record) {
            $this->records[] = $record;
        }

        return $this;
    }

    /** Make every search throw, to prove a failing group does not break the palette. */
    public function failWith(\Throwable $e): static
    {
        $this->failWith = $e;

        return $this;
    }

    public function search(Request $request, SearchQuery $query): array
    {
        $this->queries[] = $query;

        if ($this->failWith instanceof \Throwable) {
            throw $this->failWith;
        }

        $needle = mb_strtolower($query->term);

        return array_slice(array_values(array_filter(
            $this->records,
            static fn (SearchResult $record): bool => str_contains(mb_strtolower($record->title), $needle),
        )), 0, $query->limit);
    }

    /** @return list<SearchQuery> */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * Assert a search ran for `$term`, and, when given, in this context (level key => id).
     *
     * @param  array<string, string>|null  $context
     */
    public function assertSearched(string $term, ?array $context = null): void
    {
        $matching = array_filter($this->queries, static fn (SearchQuery $query): bool => $query->term === $term
            && ($context === null || array_map(static fn ($item): string => $item->id, $query->context) === $context));

        Assert::assertNotEmpty($matching, "No search for [{$term}] in the expected context.");
    }

    public function assertNotSearched(): void
    {
        Assert::assertSame([], $this->queries, 'The search provider was asked.');
    }
}
