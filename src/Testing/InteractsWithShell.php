<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Testing;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Shell\ShellBuilder;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compose into a test case to describe a shell with fakes and assert what it shares:
 *
 *     $this->shellLevels($orgs, $projects);
 *     $this->assertShell($this->get('/orgs/cbox'))->assertLevel('organization', 'org_1');
 *
 * One HTTP test is one application, so scoped bindings (`$app->scoped()`) keep the answers
 * they gave the first request for every request after it. Call {@see freshShellRequest()}
 * before a request that must start clean, as each request does in production:
 *
 *     $this->actingAs($owner)->get('/orgs/cbox');
 *     $this->freshShellRequest()->actingAs($viewer)->get('/orgs/cbox');
 */
trait InteractsWithShell
{
    protected function shellLevels(ContextLevel ...$levels): ShellBuilder
    {
        return app(ShellBuilder::class)->levels(...$levels);
    }

    /**
     * Drop the scoped instances the previous request resolved (an access check, the current
     * user, a per-request cache), so the next request builds them, and its shell, afresh.
     * Singletons and what the test set up with `actingAs()` stay.
     */
    protected function freshShellRequest(): static
    {
        app()->forgetScopedInstances();

        return $this;
    }

    /** @param TestResponse<Response> $response */
    protected function assertShell(TestResponse $response): AssertableShell
    {
        return AssertableShell::fromResponse($response);
    }
}
