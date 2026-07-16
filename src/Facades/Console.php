<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Facades;

use Cbox\Console\Kit\ConsoleManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cbox\Console\Kit\Contracts\NavRegistry nav()
 * @method static \Cbox\Console\Kit\Contracts\FeatureRegistry features()
 * @method static \Cbox\Console\Kit\Contracts\SlotRegistry slots()
 * @method static bool featureActive(string $key)
 * @method static void dashboardCard(string|\Closure $content, int $order = 100)
 *
 * @see ConsoleManager
 */
final class Console extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConsoleManager::class;
    }
}
