<?php

declare(strict_types=1);

use Foxws\Shaka\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Check if Shaka Packager binary is available for testing
 */
function hasPackager(): bool
{
    $binary = config('laravel-shaka.packager.binaries', 'packager');

    return file_exists($binary) && is_executable($binary);
}

/**
 * Skip test if Shaka Packager is not installed
 */
function skipIfNoPackager(): void
{
    if (! hasPackager()) {
        test()->markTestSkipped('Shaka Packager binary not available');
    }
}
