<?php

declare(strict_types=1);

namespace Cbox\Console\Kit\Shell;

enum BannerKind: string
{
    case Sandbox = 'sandbox';
    case Impersonation = 'impersonation';
    case Notice = 'notice';
}
