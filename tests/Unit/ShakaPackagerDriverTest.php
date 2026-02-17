<?php

declare(strict_types=1);

use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('laravel-shaka.packager.binaries', 'packager');
    Config::set('laravel-shaka.timeout', 3600);
});

it('can create driver with valid configuration', function () {
    $driver = ShakaPackager::create();

    expect($driver)->toBeInstanceOf(ShakaPackager::class);
    expect($driver->getName())->toBe('packager');
});

it('can get and set timeout', function () {
    $driver = ShakaPackager::create();

    expect($driver->getTimeout())->toBe(3600);

    $driver->setTimeout(7200);

    expect($driver->getTimeout())->toBe(7200);
});

it('can get binary path from config', function () {
    $driver = ShakaPackager::create();

    expect($driver->getBinaryPath())->toBe('packager');
});
