<?php

declare(strict_types=1);

use Cbox\Console\Kit\Branding\Branding;
use Cbox\Console\Kit\Branding\NullBrandingResolver;
use Cbox\Console\Kit\ConsoleManager;
use Cbox\Console\Kit\Contracts\BrandingResolver;
use Cbox\Console\Kit\Facades\Console;
use Illuminate\Support\Facades\Blade;

it('resolves an empty branding by default (the shell keeps its static CSS)', function (): void {
    expect(app(BrandingResolver::class))->toBeInstanceOf(NullBrandingResolver::class)
        ->and(Console::branding()->isEmpty())->toBeTrue()
        ->and(Console::branding()->styleTag())->toBe('');
});

it('honours a plugin-bound branding resolver', function (): void {
    app()->instance(BrandingResolver::class, new class implements BrandingResolver
    {
        public function resolve(): Branding
        {
            return new Branding(
                tokens: ['--primary' => '#0a2540', '--ring' => 'oklch(0.45 0.16 258)'],
                appName: 'Acme ID',
            );
        }
    });
    app()->forgetInstance(ConsoleManager::class);

    $branding = Console::branding();

    expect($branding->isEmpty())->toBeFalse()
        ->and($branding->appName)->toBe('Acme ID')
        ->and($branding->tokens())->toBe(['--primary' => '#0a2540', '--ring' => 'oklch(0.45 0.16 258)'])
        ->and($branding->styleTag())->toBe('<style>:root{--primary:#0a2540;--ring:oklch(0.45 0.16 258)}</style>');
});

it('renders the current branding through the @consoleBrandingStyle directive', function (): void {
    app()->instance(BrandingResolver::class, new class implements BrandingResolver
    {
        public function resolve(): Branding
        {
            return new Branding(tokens: ['--accent' => '#123456']);
        }
    });
    app()->forgetInstance(ConsoleManager::class);

    expect(trim(Blade::render('@consoleBrandingStyle')))
        ->toBe('<style>:root{--accent:#123456}</style>');
});

it('emits nothing through the directive when no branding is bound', function (): void {
    expect(trim(Blade::render('@consoleBrandingStyle')))->toBe('');
});

it('drops tokens with an unsafe name (deny-by-default)', function (): void {
    $branding = new Branding(tokens: [
        '--primary' => '#0a2540',
        'color: red; }' => '#fff',          // not a custom-property name
        '--BAD_NAME' => '#fff',             // uppercase / underscore
        '--x</style>' => '#fff',            // markup in the name
    ]);

    expect($branding->tokens())->toBe(['--primary' => '#0a2540']);
});

it('rejects values that try to break out of the style declaration', function (string $value): void {
    $branding = new Branding(tokens: ['--primary' => $value]);

    expect($branding->tokens())->toBe([])
        ->and($branding->styleTag())->toBe('');
})->with([
    'closing brace + rule' => ['red}body{display:none'],
    'extra declaration' => ['#fff;position:fixed'],
    'style tag injection' => ['#fff</style><script>alert(1)</script>'],
    'url()' => ['url(https://evil.example/x.png)'],
    'css comment' => ['#fff/*x*/'],
    'expression' => ['expression(alert(1))'],
    'at-rule' => ['#fff@import'],
    'quotes' => ['"#fff"'],
]);

it('accepts hex and functional colour notation', function (string $value): void {
    $branding = new Branding(tokens: ['--primary' => $value]);

    expect($branding->tokens())->toBe(['--primary' => $value]);
})->with([
    'short hex' => ['#abc'],
    'long hex' => ['#0a2540'],
    'hex with alpha' => ['#0a2540ff'],
    'oklch' => ['oklch(0.45 0.16 258)'],
    'oklch with alpha' => ['oklch(0.45 0.16 258 / 0.3)'],
    'rgb' => ['rgb(10, 37, 64)'],
    'hsl' => ['hsl(210, 73%, 15%)'],
]);

it('restricts asset urls to relative, http(s) or data:image', function (): void {
    $ok = new Branding(logoUrl: '/brand/logo.svg', faviconUrl: 'https://cdn.example/f.ico');
    expect($ok->logoUrl)->toBe('/brand/logo.svg')
        ->and($ok->faviconUrl)->toBe('https://cdn.example/f.ico');

    $bad = new Branding(logoUrl: 'javascript:alert(1)', faviconUrl: '//evil.example/f.ico');
    expect($bad->logoUrl)->toBeNull()
        ->and($bad->faviconUrl)->toBeNull();
});
