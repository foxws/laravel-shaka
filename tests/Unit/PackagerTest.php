<?php

declare(strict_types=1);

use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\ShakaPackager;

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

it('fresh instance has same driver', function () {
    $driver = mock(ShakaPackager::class);

    $packager1 = new Packager($driver);
    $packager2 = $packager1->fresh();

    expect($packager2->getDriver())->toBe($packager1->getDriver());
});
