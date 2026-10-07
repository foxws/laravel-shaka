<?php

declare(strict_types=1);

use Foxws\Shaka\ShakaExecutable;

it('runs packager from the PATH by default', function (): void {
    expect(ShakaExecutable::Packager->identifier())->toBe('packager')
        ->and(ShakaExecutable::Packager->configuredPath())->toBe('packager')
        ->and(ShakaExecutable::Packager->environmentKey())->toBe('SHAKA_PACKAGER_BINARY')
        ->and(ShakaExecutable::Packager->versionArguments())->toBe(['--version']);
});

it('uses the configured binary', function (): void {
    config(['shaka.binary' => '/opt/shaka/packager']);

    expect(ShakaExecutable::Packager->configuredPath())->toBe('/opt/shaka/packager');
});

it('falls back to the PATH when the configured binary is empty', function (): void {
    config(['shaka.binary' => '']);

    expect(ShakaExecutable::Packager->configuredPath())->toBe('packager');
});
