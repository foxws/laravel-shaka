<?php

declare(strict_types=1);

namespace Foxws\Shaka;

/**
 * The DRM systems Shaka Packager can signal in manifests (--protection_systems).
 */
enum ProtectionSystem: string
{
    case Widevine = 'Widevine';
    case PlayReady = 'PlayReady';
    case FairPlay = 'FairPlay';
    case Marlin = 'Marlin';
    case CommonSystem = 'CommonSystem';
}
