<?php

declare(strict_types=1);

use Foxws\Shaka\Support\CommandBuilder;
use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\ShakaPackager;
use Psr\Log\LoggerInterface;

it('filters sensitive encryption keys from logs', function () {
    $logger = mock(LoggerInterface::class);
    $driver = mock(ShakaPackager::class);

    // Mock the driver to return a successful result
    $driver->shouldReceive('command')
        ->once()
        ->andReturn(['exit_code' => 0, 'output' => 'success']);

    $packager = new Packager($driver, $logger);

    // Create a builder with encryption options including sensitive keys
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/output.mp4')
        ->withEncryption([
            'keys' => 'label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef',
            'key_server_url' => 'https://example.com/license',
        ]);

    // Expect the logger to be called with redacted sensitive data
    $logger->shouldReceive('info')
        ->once()
        ->with('Starting packaging operation with builder', \Mockery::on(function ($context) {
            // Check that sensitive keys are redacted
            expect($context)->toHaveKey('options');
            expect($context['options'])->toHaveKey('keys');
            expect($context['options']['keys'])->toBe('[REDACTED]');
            // Check that non-sensitive data is still present
            expect($context['options'])->toHaveKey('key_server_url');
            expect($context['options']['key_server_url'])->toBe('https://example.com/license');

            return true;
        }));

    $logger->shouldReceive('info')
        ->once()
        ->with('Packaging operation completed');

    $packager->packageWithBuilder($builder);
});

it('does not log sensitive keys in export method', function () {
    $logger = mock(LoggerInterface::class);
    $driver = mock(ShakaPackager::class);

    // Mock the driver to return a successful result
    $driver->shouldReceive('command')
        ->once()
        ->andReturn(['exit_code' => 0, 'output' => 'success']);

    $packager = new Packager($driver, $logger);

    // We need to set up a media collection and add streams
    // For this test, we'll use reflection to access the protected filterSensitiveOptions method
    $reflection = new ReflectionClass($packager);
    $method = $reflection->getMethod('filterSensitiveOptions');
    $method->setAccessible(true);

    // Test the filter method directly
    $options = [
        'keys' => 'label=:key_id=abc:key=def',
        'key' => 'secretkey123',
        'key_id' => 'keyid123',
        'pssh' => 'psshdata',
        'protection_systems' => 'widevine',
        'raw_key' => 'rawkeydata',
        'iv' => 'ivdata',
        'enable_raw_key_encryption' => true,
        'key_server_url' => 'https://example.com/license',
        'mpd_output' => '/tmp/output.mpd',
    ];

    $filtered = $method->invoke($packager, $options);

    // Verify all sensitive keys are redacted
    expect($filtered['keys'])->toBe('[REDACTED]');
    expect($filtered['key'])->toBe('[REDACTED]');
    expect($filtered['key_id'])->toBe('[REDACTED]');
    expect($filtered['pssh'])->toBe('[REDACTED]');
    expect($filtered['protection_systems'])->toBe('[REDACTED]');
    expect($filtered['raw_key'])->toBe('[REDACTED]');
    expect($filtered['iv'])->toBe('[REDACTED]');

    // Verify non-sensitive data is preserved
    expect($filtered['enable_raw_key_encryption'])->toBe(true);
    expect($filtered['key_server_url'])->toBe('https://example.com/license');
    expect($filtered['mpd_output'])->toBe('/tmp/output.mpd');
});
