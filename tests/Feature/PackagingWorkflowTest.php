<?php

declare(strict_types=1);

use Foxws\Shaka\Facades\Shaka;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Setup test fixtures
    Storage::fake('test');
    Storage::fake('export');
});

it('can package media with HLS output', function () {
    // This test requires actual media files and Shaka Packager to be installed
    // Mark as skipped if packager is not available
    if (! file_exists(config('laravel-shaka.packager.binaries'))) {
        $this->markTestSkipped('Shaka Packager binary not found');
    }

    // Test implementation would go here
    expect(true)->toBeTrue();
})->skip('Requires actual media files and packager binary');

it('can package media with DASH output', function () {
    if (! file_exists(config('laravel-shaka.packager.binaries'))) {
        $this->markTestSkipped('Shaka Packager binary not found');
    }

    expect(true)->toBeTrue();
})->skip('Requires actual media files and packager binary');

it('can switch between disks during packaging', function () {
    if (! file_exists(config('laravel-shaka.packager.binaries'))) {
        $this->markTestSkipped('Shaka Packager binary not found');
    }

    expect(true)->toBeTrue();
})->skip('Requires actual media files and packager binary');
