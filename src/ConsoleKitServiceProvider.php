<?php

declare(strict_types=1);

namespace Cbox\Console\Kit;

use Cbox\Console\Kit\Branding\NullBrandingResolver;
use Cbox\Console\Kit\Context\NullCurrentContext;
use Cbox\Console\Kit\Contracts\BrandingResolver;
use Cbox\Console\Kit\Contracts\CurrentContext;
use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Cbox\Console\Kit\Contracts\NavRegistry;
use Cbox\Console\Kit\Contracts\ShellPreferenceStore;
use Cbox\Console\Kit\Contracts\SlotRegistry;
use Cbox\Console\Kit\Features\DefaultFeatureRegistry;
use Cbox\Console\Kit\Http\Middleware\HandleShell;
use Cbox\Console\Kit\Http\Middleware\RequireFeature;
use Cbox\Console\Kit\Nav\DefaultNavRegistry;
use Cbox\Console\Kit\Shell\CookieShellPreferences;
use Cbox\Console\Kit\Shell\ShellBuilder;
use Cbox\Console\Kit\Slots\DefaultSlotRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View as Views;
use Illuminate\Support\ServiceProvider;

class ConsoleKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singletons so registrations from every provider accumulate into one registry.
        $this->app->singleton(NavRegistry::class, DefaultNavRegistry::class);
        $this->app->singleton(FeatureRegistry::class, DefaultFeatureRegistry::class);
        $this->app->singleton(SlotRegistry::class, DefaultSlotRegistry::class);

        // bindIf so a host can bind its own CurrentContext; else the null context.
        $this->app->bindIf(CurrentContext::class, NullCurrentContext::class);

        // bindIf so a white-label plugin can override branding; else the empty
        // resolver, which keeps the shell on its static CSS (deny-by-default).
        $this->app->bindIf(BrandingResolver::class, NullBrandingResolver::class);

        // bindIf so an app can keep shell preferences elsewhere (Cbox ID, its own table).
        $this->app->bindIf(ShellPreferenceStore::class, CookieShellPreferences::class);

        // One shell description per app, filled in by a service provider and read per request.
        $this->app->singleton(ShellBuilder::class);

        $this->app->singleton(ConsoleManager::class, static fn (Application $app): ConsoleManager => new ConsoleManager(
            $app->make(NavRegistry::class),
            $app->make(FeatureRegistry::class),
            $app->make(SlotRegistry::class),
            $app->make(CurrentContext::class),
            $app->make(BrandingResolver::class),
            static fn (): ShellBuilder => $app->make(ShellBuilder::class),
        ));
    }

    public function boot(): void
    {
        // @consoleSlot('name') / @consoleSlot('name', [...]) — renders a slot's HTML.
        Blade::directive('consoleSlot', static fn (string $expression): string => '<?php echo app(\Cbox\Console\Kit\Contracts\SlotRegistry::class)->render('.$expression.'); ?>');

        // @consoleBrandingStyle — echoes the current Branding's validated <style> tag
        // (empty when no branding is bound). Safe unescaped: the VO only holds tokens
        // it proved safe. Drop it in the shell's <head> after the static stylesheet.
        Blade::directive('consoleBrandingStyle', static fn (): string => '<?php echo app(\Cbox\Console\Kit\ConsoleManager::class)->branding()->styleTag(); ?>');

        // Route guard: ->middleware('console.feature:billing')
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('console.feature', RequireFeature::class);

        // Shares the `shell` prop: ->middleware('console.shell')
        $router->aliasMiddleware('console.shell', HandleShell::class);

        // Blade views rendered under `console.shell` get `$shell`, built on first use.
        Views::composer('*', function (View $view): void {
            $request = $this->app->bound('request') ? $this->app->make('request') : null;
            $build = $request instanceof Request ? $request->attributes->get(HandleShell::ATTRIBUTE) : null;

            if ($build instanceof \Closure && ! array_key_exists('shell', $view->getData())) {
                $view->with('shell', $build());
            }
        });

        // Theme, rail and Help are written by the page itself before the round trip ends,
        // so they are plain cookies (the values are not secret; the reader validates them).
        EncryptCookies::except(CookieShellPreferences::CLIENT_COOKIES);
    }
}
