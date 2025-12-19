<?php

declare(strict_types=1);

use Foxws\Shaka\Support\Packager\Packager;
use Foxws\Shaka\Support\Packager\ShakaPackager;

it('can create packager instance', function () {
    $driver = mock(ShakaPackager::class);

    $packager = new Packager($driver);

    expect($packager)->toBeInstanceOf(Packager::class);
    expect($packager->getDriver())->toBe($driver);
});

it('can set and get driver', function () {
    $driver1 = mock(ShakaPackager::class);
    $driver2 = mock(ShakaPackager::class);

    $packager = new Packager($driver1);

    expect($packager->getDriver())->toBe($driver1);

    $packager->setDriver($driver2);

    expect($packager->getDriver())->toBe($driver2);
});

it('can create fresh instance', function () {
    $driver = mock(ShakaPackager::class);

    $packager1 = new Packager($driver);
    $packager2 = $packager1->fresh();

    expect($packager2)->toBeInstanceOf(Packager::class);
    expect($packager2)->not->toBe($packager1);
    expect($packager2->getDriver())->toBe($driver);
});

it('can create packager using static create method', function () {
    config(['shaka.packager.binaries' => '/usr/local/bin/packager']);
    config(['shaka.timeout' => 3600]);

    $packager = Packager::create();

    expect($packager)->toBeInstanceOf(Packager::class);
    expect($packager->getDriver())->toBeInstanceOf(ShakaPackager::class);
})->skip('Requires actual binary to be present');
