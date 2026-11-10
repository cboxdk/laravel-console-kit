<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

/**
 * The colored square beside an environment. App-defined: the shell has no rule about
 * which environments are production (spec §5.1a).
 */
enum Tone: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
    case Neutral = 'neutral';
}
