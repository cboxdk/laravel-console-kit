<?php

declare(strict_types=1);

use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ShellAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    shellApp();
    $this->shellLevels(...shellLevels())->app('billing', 'Cbox Billing', '/orgs');
});

function shellAccount(string $url = '/orgs/cbox'): ?array
{
    return test()->get($url, inertiaVisit())->assertOk()->json('props.shell.account');
}

function accountRoutes(): void
{
    Route::middleware('web')->group(static function (): void {
        Route::get('/settings/profile', fn () => 'profile')->name('profile');
        Route::get('/login/switch', fn () => 'switch')->name('login.switch');
        Route::post('/logout', fn () => 'bye')->name('logout');
        Route::get('/orgs/{org}/me', fn () => 'me')->name('orgs.me');
    });
    app('router')->getRoutes()->refreshNameLookups();
}

it('leaves account out when nothing resolves, so a 0.2 prop is unchanged', function (): void {
    expect(shellAccount())->toBeNull();
});

it('defaults to the profile, login.switch and logout routes', function (): void {
    accountRoutes();

    expect(shellAccount())->toBe([
        'profileUrl' => '/settings/profile',
        'switchAccountUrl' => '/login/switch',
        'signOutUrl' => '/logout',
    ]);
});

it('prefers Cbox ID\'s profile page when laravel-id-client is installed', function (): void {
    accountRoutes();
    app()->instance('Cbox\\Id\\Client\\IdentityClient', new class
    {
        public function profileUrl(): string
        {
            return 'https://acme.cboxid.com/account';
        }
    });
    // The stand-in is only used when the real class exists.
    if (! class_exists('Cbox\\Id\\Client\\IdentityClient')) {
        class_alias(get_class(app('Cbox\\Id\\Client\\IdentityClient')), 'Cbox\\Id\\Client\\IdentityClient');
    }

    expect(shellAccount()['profileUrl'])->toBe('https://acme.cboxid.com/account');
});

it('takes route names, paths, URLs and closures from the app, and hides a row with false', function (): void {
    accountRoutes();
    Console::shell()->account(
        profileUrl: 'https://id.cbox.dk/account?return_to=billing',
        switchAccountUrl: fn (Request $request): string => '/login/switch?from='.$request->route('org'),
        signOutUrl: 'logout',
        signedInWith: 'Signed in with Cbox ID · Production EU',
        languages: ['en' => 'English', 'da' => 'Dansk'],
        formats: ['da-DK' => 'Danish (Denmark)'],
    );

    expect(shellAccount())->toBe([
        'profileUrl' => 'https://id.cbox.dk/account?return_to=billing',
        'switchAccountUrl' => '/login/switch?from=cbox',
        'signOutUrl' => '/logout',
        'signedInWith' => 'Signed in with Cbox ID · Production EU',
        'languages' => [['code' => 'en', 'label' => 'English'], ['code' => 'da', 'label' => 'Dansk']],
        'formats' => [['code' => 'da-DK', 'label' => 'Danish (Denmark)']],
    ]);

    Console::shell()->account(profileUrl: 'orgs.me', switchAccountUrl: false, signOutUrl: '/sign-out', signOutMethod: 'get');

    expect(shellAccount())->toBe([
        // A route name is built with the current page's parameters.
        'profileUrl' => '/orgs/cbox/me',
        'signOutUrl' => '/sign-out',
        'signOutMethod' => 'get',
    ]);
});

it('never links off-site over plain http or a script url', function (): void {
    Console::shell()->account(profileUrl: 'javascript:alert(1)', switchAccountUrl: 'http://evil.test', signOutUrl: '//evil.test/logout');

    expect(shellAccount())->toBeNull();
});

it('serialises an empty account as nothing', function (): void {
    expect((new ShellAccount)->isEmpty())->toBeTrue()
        ->and((new ShellAccount(signOutUrl: '/logout'))->toArray())->toBe(['signOutUrl' => '/logout']);
});
