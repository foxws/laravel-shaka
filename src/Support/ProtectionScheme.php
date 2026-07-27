<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

/**
 * Shaka Packager content protection scheme (--protection_scheme).
 *
 * Pattern-based schemes (Cens, Cbcs) apply to video streams only.
 */
enum ProtectionScheme: string
{
    case Cenc = 'cenc';
    case Cbc1 = 'cbc1';
    case Cens = 'cens';
    case Cbcs = 'cbcs';
}
