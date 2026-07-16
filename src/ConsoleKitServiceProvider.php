<?php

declare(strict_types=1);

namespace Cbox\Console\Kit;

use Cbox\Console\Kit\Branding\NullBrandingResolver;
use Cbox\Console\Kit\Context\NullCurrentContext;
use Cbox\Console\Kit\Contracts\BrandingResolver;
use Cbox\Console\Kit\Contracts\CurrentContext;
use Cbox\Console\Kit\Contracts\FeatureRegistry;
use Cbox\Console\Kit\Contracts\NavRegistry;
use Cbox\Console\Kit\Contracts\SlotRegistry;
use Cbox\Console\Kit\Features\DefaultFeatureRegistry;
use Cbox\Console\Kit\Http\Middleware\RequireFeature;
use Cbox\Console\Kit\Nav\DefaultNavRegistry;
use Cbox\Console\Kit\Slots\DefaultSlotRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class ConsoleKitServiceProvider extends ServiceProvider
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

        $this->app->singleton(ConsoleManager::class, static fn (Application $app): ConsoleManager => new ConsoleManager(
            $app->make(NavRegistry::class),
            $app->make(FeatureRegistry::class),
            $app->make(SlotRegistry::class),
            $app->make(CurrentContext::class),
            $app->make(BrandingResolver::class),
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
    }
}
