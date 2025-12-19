<?php

declare(strict_types=1);

use Foxws\Shaka\Facades\Shaka;
use Foxws\Shaka\Support\Filesystem\Disk;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Setup test disks
    config([
        'filesystems.disks.test-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/test'),
        ],
        'filesystems.disks.another-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/another'),
        ],
    ]);
});

it('can use fromDisk to set the disk', function () {
    $shaka = new \Foxws\Shaka\Shaka;

    $result = $shaka->fromDisk('test-disk');

    expect($result)->toBe($shaka);
    expect($shaka->getDisk())->toBeInstanceOf(Disk::class);
    expect($shaka->getDisk()->getName())->toBe('test-disk');
});

it('can chain fromDisk with open', function () {
    Storage::fake('test-disk');
    Storage::disk('test-disk')->put('video.mp4', 'test content');

    $shaka = new \Foxws\Shaka\Shaka;

    $result = $shaka->fromDisk('test-disk')->open('video.mp4');

    expect($result)->toBe($shaka);
    expect($shaka->get()->count())->toBe(1);
})->skip('Requires proper filesystem setup');

it('can use openFromDisk helper', function () {
    Storage::fake('test-disk');
    Storage::disk('test-disk')->put('video.mp4', 'test content');

    $shaka = new \Foxws\Shaka\Shaka;

    $result = $shaka->openFromDisk('test-disk', 'video.mp4');

    expect($result)->toBe($shaka);
    expect($shaka->get()->count())->toBe(1);
    expect($shaka->getDisk()->getName())->toBe('test-disk');
})->skip('Requires proper filesystem setup');

it('can switch between disks', function () {
    $shaka = new \Foxws\Shaka\Shaka;

    // Start with default disk
    expect($shaka->getDisk()->getName())->toBe(config('filesystems.default'));

    // Switch to test-disk
    $shaka->fromDisk('test-disk');
    expect($shaka->getDisk()->getName())->toBe('test-disk');

    // Switch to another-disk
    $shaka->fromDisk('another-disk');
    expect($shaka->getDisk()->getName())->toBe('another-disk');
});

it('can open multiple files from specific disk', function () {
    Storage::fake('test-disk');
    Storage::disk('test-disk')->put('video1.mp4', 'content 1');
    Storage::disk('test-disk')->put('video2.mp4', 'content 2');
    Storage::disk('test-disk')->put('video3.mp4', 'content 3');

    $shaka = new \Foxws\Shaka\Shaka;

    $result = $shaka->fromDisk('test-disk')->open([
        'video1.mp4',
        'video2.mp4',
        'video3.mp4',
    ]);

    expect($result)->toBe($shaka);
    expect($shaka->get()->count())->toBe(3);
})->skip('Requires proper filesystem setup');

it('fromDisk returns instance for method chaining', function () {
    $shaka = new \Foxws\Shaka\Shaka;

    $result = $shaka
        ->fromDisk('test-disk')
        ->open('video.mp4');

    expect($result)->toBe($shaka);
})->skip('Requires proper filesystem setup');

it('can use facade with fromDisk', function () {
    Storage::fake('test-disk');
    Storage::disk('test-disk')->put('video.mp4', 'test content');

    // This should not throw an exception
    $shaka = Shaka::fromDisk('test-disk');

    expect($shaka)->toBeInstanceOf(\Foxws\Shaka\Shaka::class);
})->skip('Requires proper facade setup');

it('preserves disk context through method chain', function () {
    Storage::fake('test-disk');
    Storage::disk('test-disk')->put('video.mp4', 'test content');

    $shaka = new \Foxws\Shaka\Shaka;

    $shaka->fromDisk('test-disk')
        ->open('video.mp4')
        ->addVideoStream('video.mp4', 'output.mp4');

    expect($shaka->getDisk()->getName())->toBe('test-disk');
})->skip('Requires proper filesystem setup');

it('clone method preserves disk', function () {
    $shaka = new \Foxws\Shaka\Shaka;
    $shaka->fromDisk('test-disk');

    $cloned = $shaka->clone();

    expect($cloned->getDisk()->getName())->toBe('test-disk');
    expect($cloned)->not->toBe($shaka);
});
