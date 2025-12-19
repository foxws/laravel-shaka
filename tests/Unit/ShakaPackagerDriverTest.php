<?php

declare(strict_types=1);

use Foxws\Shaka\Exceptions\ExecutableNotFoundException;
use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('shaka.packager.binaries', '/usr/local/bin/packager');
    Config::set('shaka.timeout', 3600);
});

it('can create driver instance', function () {
    $driver = ShakaPackager::create();

    expect($driver)->toBeInstanceOf(ShakaPackager::class);
    expect($driver->getName())->toBe('packager');
});

it('throws exception when binary not found', function () {
    Config::set('shaka.packager.binaries', '/nonexistent/packager');

    ShakaPackager::create();
})->throws(ExecutableNotFoundException::class);

it('can get and set timeout', function () {
    $driver = ShakaPackager::create();

    expect($driver->getTimeout())->toBe(3600);

    $driver->setTimeout(7200);

    expect($driver->getTimeout())->toBe(7200);
});

it('can get binary path', function () {
    $driver = ShakaPackager::create();

    expect($driver->getBinaryPath())->toBe('/usr/local/bin/packager');
});
