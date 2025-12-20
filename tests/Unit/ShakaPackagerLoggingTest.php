<?php

declare(strict_types=1);

use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Support\Facades\Process;
use Psr\Log\LoggerInterface;

it('redacts sensitive encryption keys from debug logs', function () {
    $logger = mock(LoggerInterface::class);

    // Create a ShakaPackager instance with the mocked logger
    $packager = new ShakaPackager('/usr/local/bin/packager', $logger, 3600);

    // Mock the Process facade to return a successful result
    Process::fake([
        '*' => Process::result(
            output: 'success',
            errorOutput: '',
            exitCode: 0
        ),
    ]);

    // Create a command with sensitive encryption options
    $command = 'in=input.mp4,stream=video,output=output.mp4 '
        .'--enable_raw_key_encryption '
        .'--keys=label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef '
        .'--key_server_url=https://example.com/license';

    // Expect the logger to be called with redacted sensitive data
    $logger->shouldReceive('debug')
        ->once()
        ->with('Executing packager command', \Mockery::on(function ($context) {
            // Verify the command is logged
            expect($context)->toHaveKey('command');

            // Verify sensitive keys are redacted
            expect($context['command'])->toContain('--keys=[REDACTED]');
            expect($context['command'])->not->toContain('abcdef0123456789abcdef0123456789');
            expect($context['command'])->not->toContain('0123456789abcdef0123456789abcdef');

            // Verify non-sensitive data is still present
            expect($context['command'])->toContain('--enable_raw_key_encryption');
            expect($context['command'])->toContain('--key_server_url=https://example.com/license');

            return true;
        }));

    $logger->shouldReceive('debug')
        ->once()
        ->with('Packager command completed', \Mockery::any());

    $packager->command($command);
});

it('redacts multiple sensitive options from logs', function () {
    $logger = mock(LoggerInterface::class);

    $packager = new ShakaPackager('/usr/local/bin/packager', $logger, 3600);

    Process::fake([
        '*' => Process::result(
            output: 'success',
            errorOutput: '',
            exitCode: 0
        ),
    ]);

    // Command with multiple sensitive options
    $command = '--key=secretkey123 --key_id=keyid456 --pssh=psshdata789 --raw_key=rawkey012 --iv=ivdata345 --protection_systems=widevine';

    $logger->shouldReceive('debug')
        ->once()
        ->with('Executing packager command', \Mockery::on(function ($context) {
            // Verify all sensitive options are redacted
            expect($context['command'])->toContain('--key=[REDACTED]');
            expect($context['command'])->toContain('--key_id=[REDACTED]');
            expect($context['command'])->toContain('--pssh=[REDACTED]');
            expect($context['command'])->toContain('--raw_key=[REDACTED]');
            expect($context['command'])->toContain('--iv=[REDACTED]');
            expect($context['command'])->toContain('--protection_systems=[REDACTED]');

            // Verify actual values are not present
            expect($context['command'])->not->toContain('secretkey123');
            expect($context['command'])->not->toContain('keyid456');
            expect($context['command'])->not->toContain('psshdata789');
            expect($context['command'])->not->toContain('rawkey012');
            expect($context['command'])->not->toContain('ivdata345');
            expect($context['command'])->not->toContain('widevine');

            return true;
        }));

    $logger->shouldReceive('debug')
        ->once()
        ->with('Packager command completed', \Mockery::any());

    $packager->command($command);
});

it('redacts sensitive data from error logs', function () {
    $logger = mock(LoggerInterface::class);

    $packager = new ShakaPackager('/usr/local/bin/packager', $logger, 3600);

    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Command failed',
            exitCode: 1
        ),
    ]);

    $command = '--keys=label=:key_id=abc123:key=def456';

    $logger->shouldReceive('debug')
        ->once()
        ->with('Executing packager command', \Mockery::on(function ($context) {
            expect($context['command'])->toContain('--keys=[REDACTED]');
            expect($context['command'])->not->toContain('abc123');
            expect($context['command'])->not->toContain('def456');

            return true;
        }));

    // Expect error log with redacted command
    $logger->shouldReceive('error')
        ->once()
        ->with(\Mockery::any(), \Mockery::on(function ($context) {
            expect($context)->toHaveKey('command');
            expect($context['command'])->toContain('--keys=[REDACTED]');
            expect($context['command'])->not->toContain('abc123');
            expect($context['command'])->not->toContain('def456');

            return true;
        }));

    try {
        $packager->command($command);
    } catch (\Foxws\Shaka\Exceptions\RuntimeException $e) {
        // Expected exception
        expect($e->getMessage())->toContain('Packager command failed');
    }
});

it('preserves non-sensitive options in logs', function () {
    $logger = mock(LoggerInterface::class);

    $packager = new ShakaPackager('/usr/local/bin/packager', $logger, 3600);

    Process::fake([
        '*' => Process::result(
            output: 'success',
            errorOutput: '',
            exitCode: 0
        ),
    ]);

    $command = 'in=input.mp4 --segment_duration=6 --mpd_output=output.mpd --hls_master_playlist=master.m3u8';

    $logger->shouldReceive('debug')
        ->once()
        ->with('Executing packager command', \Mockery::on(function ($context) {
            // Verify non-sensitive options are preserved
            expect($context['command'])->toContain('in=input.mp4');
            expect($context['command'])->toContain('--segment_duration=6');
            expect($context['command'])->toContain('--mpd_output=output.mpd');
            expect($context['command'])->toContain('--hls_master_playlist=master.m3u8');

            return true;
        }));

    $logger->shouldReceive('debug')
        ->once()
        ->with('Packager command completed', \Mockery::any());

    $packager->command($command);
});
