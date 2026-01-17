<?php

declare(strict_types=1);

use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');

    // Create a test video file
    Storage::disk('local')->put('test.mp4', 'fake video content');
});

it('generates AES encryption with default settings', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $this->app->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class);

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    $keyData = $packager->withAESEncryption();

    // Verify key data structure
    expect($keyData)->toBeArray()
        ->toHaveKeys(['key', 'key_id', 'file_path'])
        ->and(strlen($keyData['key']))->toBe(32)
        ->and(strlen($keyData['key_id']))->toBe(32)
        ->and(file_exists($keyData['file_path']))->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
});

it('copies encryption key to temp directory for export', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $this->app->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class);

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    $keyData = $packager->withAESEncryption('', 'my-encryption.key');

    // Key should be in cache storage
    expect($keyData['file_path'])->toStartWith(sys_get_temp_dir().'/test-cache/');

    // Key should also be copied to temp directory
    $tempDir = (new ReflectionClass($packager))->getMethod('getTemporaryDirectory')->invoke($packager);
    $exportKeyPath = $tempDir.DIRECTORY_SEPARATOR.'my-encryption.key';

    expect(file_exists($exportKeyPath))->toBeTrue()
        ->and(file_get_contents($exportKeyPath))->toBe(file_get_contents($keyData['file_path']));

    // Cleanup
    $tempDirs->deleteAll();
});

it('configures encryption with cbc1 protection scheme by default', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $this->app->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class);

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption();

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->toHaveKey('protection_scheme')
        ->and($options['protection_scheme'])->toBe('cbc1')
        ->and($options)->toHaveKey('clear_lead')
        ->and($options['clear_lead'])->toBe(0)
        ->and($options)->toHaveKey('hls_key_uri')
        ->and($options['hls_key_uri'])->toBe('encryption.key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('allows custom protection scheme', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $this->app->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class);

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption('', 'encryption.key', 'cbcs');

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['protection_scheme'])->toBe('cbcs');

    // Cleanup
    $tempDirs->deleteAll();
});

it('allows omitting protection scheme', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $this->app->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class);

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption('', 'encryption.key', null);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->not->toHaveKey('protection_scheme');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports custom key filename', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $this->app->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class);

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $keyData = $packager->withAESEncryption('', 'custom-key.bin');

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['hls_key_uri'])->toBe('custom-key.bin')
        ->and($keyData['file_path'])->toContain('custom-key.bin');

    // Cleanup
    $tempDirs->deleteAll();
});
