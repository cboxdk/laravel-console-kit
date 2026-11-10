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
 */
trait InteractsWithShell
{
    protected function shellLevels(ContextLevel ...$levels): ShellBuilder
    {
        return app(ShellBuilder::class)->levels(...$levels);
    }

    /** @param TestResponse<Response> $response */
    protected function assertShell(TestResponse $response): AssertableShell
    {
        return AssertableShell::fromResponse($response);
    }
}
