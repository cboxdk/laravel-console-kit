<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * The colored square beside an environment. App-defined: the shell has no rule about
 * which environments are production (spec §5.1a). `Staging`, `Preview` and `Development`
 * need cbox-ui 0.11 or newer; older versions show them as neutral.
 */
enum Tone: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
    case Neutral = 'neutral';
    case Staging = 'staging';
    case Preview = 'preview';
    case Development = 'development';
}
