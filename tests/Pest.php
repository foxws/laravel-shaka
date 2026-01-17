<?php

declare(strict_types=1);

use Foxws\Shaka\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Get the path to a test fixture file
 */
function fixture(string $file): string
{
    $path = __DIR__.'/fixtures/'.$file;

    if (! file_exists($path)) {
        throw new InvalidArgumentException("The fixture file [{$path}] does not exist.");
    }

    return $path;
}

/**
 * Check if Shaka Packager binary is available for testing
 */
function hasPackager(): bool
{
    $binary = config('laravel-shaka.packager.binaries', '/usr/local/bin/packager');

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
