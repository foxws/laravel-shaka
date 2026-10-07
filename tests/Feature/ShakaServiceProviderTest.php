<?php

declare(strict_types=1);

use Foxws\Media\Executables\Executables;
use Foxws\Media\Facades\Media;
use Foxws\Media\Packaging\PackagerManager;
use Foxws\Media\Packaging\PackagingBuilder;
use Foxws\Shaka\ShakaExecutable;
use Foxws\Shaka\ShakaPackager;
use Foxws\Shaka\ShakaServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

it('adds the shaka packager driver', function (): void {
    expect(app(PackagerManager::class)->driver('shaka'))->toBeInstanceOf(ShakaPackager::class);
});

it('packages with shaka by default when media.packager.default is shaka', function (): void {
    config(['media.packager.default' => 'shaka']);
    Storage::fake('media');
    Media::fake();

    expect(Media::fromDisk('media')->open('clip.mp4')->exportAsHLS()->packager())->toBeInstanceOf(ShakaPackager::class);
});

it('adds shaka() to opened media', function (): void {
    Storage::fake('media');
    Media::fake();

    $builder = Media::fromDisk('media')->open('clip.mp4')->shaka();

    expect($builder)->toBeInstanceOf(PackagingBuilder::class)
        ->and($builder->packager())->toBeInstanceOf(ShakaPackager::class)
        ->and($builder->media()->paths())->toBe(['clip.mp4']);
});

it('registers packager with media:info and about', function (): void {
    expect(app(Executables::class)->all())->toContain(ShakaExecutable::Packager);
});

it('merges and publishes the config', function (): void {
    expect(config('shaka.binary'))->toBe('packager')
        ->and(config('shaka.timeout'))->toBe(14400)
        ->and(array_values(ServiceProvider::pathsToPublish(ShakaServiceProvider::class, 'shaka-config')))
        ->toBe([config_path('shaka.php')]);
});
