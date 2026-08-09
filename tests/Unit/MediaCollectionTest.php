<?php

declare(strict_types=1);

use Foxws\Shaka\Filesystem\Disk;
use Foxws\Shaka\Filesystem\MediaCollection;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    config([
        'filesystems.disks.media-collection-test' => [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/media-collection-test',
        ],
    ]);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory(sys_get_temp_dir().'/media-collection-test');
});

it('sums the size of all media items', function () {
    $disk = Disk::make('media-collection-test');

    $disk->put('a.mp4', str_repeat('a', 1000));
    $disk->put('b.mp4', str_repeat('b', 2000));

    $collection = MediaCollection::make([
        $disk->makeMedia('a.mp4'),
        $disk->makeMedia('b.mp4'),
    ]);

    expect($collection->totalSize())->toBe(3000);
});

it('returns zero for an empty collection', function () {
    expect(MediaCollection::make()->totalSize())->toBe(0);
});
