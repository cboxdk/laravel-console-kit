<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\CookieShellPreferences;
use Cbox\Console\Kit\Testing\FakeContextLevel;
use Illuminate\Auth\GenericUser;

beforeEach(function (): void {
    shellApp();
    Console::shellHome();
    [$this->orgs, $this->projects, $this->environments] = shellLevels();
    $this->shellLevels($this->orgs, $this->projects, $this->environments);
});

it('lands / on the context the person last switched to', function (): void {
    $value = $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings'])
        ->getCookie(CookieShellPreferences::LAST_CONTEXT)?->getValue();

    $this->withCookie(CookieShellPreferences::LAST_CONTEXT, (string) $value)
        ->get('/')->assertRedirect('/orgs/acme/settings');
});

it('lands / on the first organization when nothing was remembered', function (): void {
    $this->get('/')->assertRedirect('/orgs/cbox');
});

it('ignores another person\'s last context in the same browser', function (): void {
    $value = $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings'])
        ->getCookie(CookieShellPreferences::LAST_CONTEXT)?->getValue();

    $this->actingAs(new GenericUser(['id' => 'usr_2', 'name' => 'Someone Else']));

    $this->withCookie(CookieShellPreferences::LAST_CONTEXT, (string) $value)
        ->get('/')->assertRedirect('/orgs/cbox');
});

it('ignores a last context the person can no longer open', function (): void {
    $value = $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings'])
        ->getCookie(CookieShellPreferences::LAST_CONTEXT)?->getValue();

    // The person left Acme since.
    $orgs = FakeContextLevel::make('organization', 'Organization', 'org')
        ->with(new ContextItem('org_1', 'Cbox', 'cbox'));
    $this->shellLevels($orgs);

    $this->withCookie(CookieShellPreferences::LAST_CONTEXT, (string) $value)
        ->get('/')->assertRedirect('/orgs/cbox');
});

it('sends a person with no organization to the organization list', function (): void {
    $this->shellLevels(FakeContextLevel::make('organization', 'Organization', 'org'));

    $this->get('/')->assertRedirect('/orgs');
});

it('needs a signed-in person', function (): void {
    app('auth')->forgetGuards();

    $this->getJson('/')->assertUnauthorized();
});
