<?php

declare(strict_types=1);

use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Support\Collection;
use Psr\Log\AbstractLogger;

beforeEach(function () {
    $this->tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-remote-input');
    app()->instance(TemporaryDirectories::class, $this->tempDirs);

    config(['laravel-shaka.force_generic_input' => true]);
});

afterEach(function () {
    $this->tempDirs->deleteAll();
});

it('downloads a remote input only once when it was already fetched', function () {
    $commands = [];
    $media = Media::make(makeRecordingS3Disk($commands), 'videos/clip.mp4');

    $media->getLocalPath();
    $safePath = $media->getSafeInputPath();

    expect(collect(commandNames($commands))->filter(fn ($name) => $name === 'GetObject'))->toHaveCount(1)
        ->and(file_get_contents($safePath))->toBe('remote media contents');
});

it('does not download remote inputs just to log them when opening', function () {
    $commands = [];
    $disk = makeRecordingS3Disk($commands);

    $logger = new class extends AbstractLogger
    {
        public Collection $records;

        public function log($level, string|Stringable $message, array $context = []): void
        {
            ($this->records ??= new Collection)->push(compact('message', 'context'));
        }
    };

    $packager = new Packager(new ShakaPackager('packager'), $logger);
    $packager->open(MediaCollection::make([Media::make($disk, 'videos/clip.mp4')]));

    expect(commandNames($commands))->not->toContain('GetObject')
        ->and($logger->records->firstWhere('message', 'Opened media collection')['context']['paths'])->toBe(['videos/clip.mp4']);
});
