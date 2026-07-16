<?php

declare(strict_types=1);

namespace Cbox\Console\Kit;

use Cbox\Console\Kit\Context\NullCurrentContext;
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

        $this->app->singleton(ConsoleManager::class, static fn (Application $app): ConsoleManager => new ConsoleManager(
            $app->make(NavRegistry::class),
            $app->make(FeatureRegistry::class),
            $app->make(SlotRegistry::class),
            $app->make(CurrentContext::class),
        ));
    }

    public function boot(): void
    {
        // @consoleSlot('name') / @consoleSlot('name', [...]) — renders a slot's HTML.
        Blade::directive('consoleSlot', static fn (string $expression): string => '<?php echo app(\Cbox\Console\Kit\Contracts\SlotRegistry::class)->render('.$expression.'); ?>');

        // Route guard: ->middleware('console.feature:billing')
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('console.feature', RequireFeature::class);
    }
}
