<?php

declare(strict_types=1);

use Foxws\Shaka\ProtectionSystem;
use Foxws\Shaka\ShakaOptions;

it('maps each option to its shaka packager name and value', function (Closure $configure, array $options): void {
    expect($configure(ShakaOptions::make())->toArray())->toBe($options);
})->with([
    'base urls' => [fn (ShakaOptions $o): ShakaOptions => $o->baseUrls(['https://a.test/', 'https://b.test/']), ['base_urls' => 'https://a.test/,https://b.test/']],
    'hls' => [fn (ShakaOptions $o): ShakaOptions => $o->hlsBaseUrl('https://cdn.test/')->hlsMediaSequenceNumber(5)->hlsStartTimeOffset(-12.5)->createSessionKeys(), [
        'hls_base_url' => 'https://cdn.test/', 'hls_media_sequence_number' => '5', 'hls_start_time_offset' => '-12.5', 'create_session_keys' => true,
    ]],
    'segments' => [fn (ShakaOptions $o): ShakaOptions => $o->startSegmentNumber(1)->transportStreamTimestampOffset(100), ['start_segment_number' => '1', 'transport_stream_timestamp_offset_ms' => '100']],
    'live dash' => [fn (ShakaOptions $o): ShakaOptions => $o->minBufferTime(2)->minimumUpdatePeriod(5)->suggestedPresentationDelay(10)->timeShiftBufferDepth(60)
        ->preservedSegmentsOutsideLiveWindow(50)->lowLatencyDashMode()->generateStaticLiveMpd()->forceClIndex(), [
            'min_buffer_time' => '2', 'minimum_update_period' => '5', 'suggested_presentation_delay' => '10', 'time_shift_buffer_depth' => '60',
            'preserved_segments_outside_live_window' => '50', 'low_latency_dash_mode' => true, 'generate_static_live_mpd' => true, 'force_cl_index' => true,
        ]],
    'utc timings' => [fn (ShakaOptions $o): ShakaOptions => $o->utcTimings(['urn:mpeg:dash:utc:http-xsdate:2014' => 'https://time.test/?iso']), ['utc_timings' => 'urn:mpeg:dash:utc:http-xsdate:2014=https://time.test/?iso']],
    'patterns' => [fn (ShakaOptions $o): ShakaOptions => $o->cryptByteBlock(1)->skipByteBlock(9), ['crypt_byte_block' => '1', 'skip_byte_block' => '9']],
    'vp9 subsamples off' => [fn (ShakaOptions $o): ShakaOptions => $o->vp9SubsampleEncryption()->vp9SubsampleEncryption(false), ['novp9_subsample_encryption' => true]],
    'protection systems' => [fn (ShakaOptions $o): ShakaOptions => $o->protectionSystems(ProtectionSystem::Widevine, ProtectionSystem::PlayReady, ProtectionSystem::Widevine)->pssh('abcd')->iv('00112233445566778899aabbccddeeff'), [
        'protection_systems' => 'Widevine,PlayReady', 'pssh' => 'abcd', 'iv' => '00112233445566778899aabbccddeeff',
    ]],
    'widevine' => [fn (ShakaOptions $o): ShakaOptions => $o->widevine('https://license.test/', 'abcd', 'policy')->maxPixels(sd: 442368, hd: 2073600)->groupId('ab')->enableEntitlementLicense(), [
        'enable_widevine_encryption' => true, 'key_server_url' => 'https://license.test/', 'content_id' => 'abcd', 'policy' => 'policy',
        'max_sd_pixels' => '442368', 'max_hd_pixels' => '2073600', 'group_id' => 'ab', 'enable_entitlement_license' => true,
    ]],
    'playready' => [fn (ShakaOptions $o): ShakaOptions => $o->playready('https://playready.test/', 'program')->keyServerTls(caFile: '/ca.pem', clientCertPrivateKeyPassword: 'secret'), [
        'enable_playready_encryption' => true, 'playready_server_url' => 'https://playready.test/', 'program_identifier' => 'program',
        'ca_file' => '/ca.pem', 'client_cert_private_key_password' => 'secret',
    ]],
    'decryption' => [fn (ShakaOptions $o): ShakaOptions => $o->decrypt()->decrypt(widevine: true), ['enable_raw_key_decryption' => true, 'enable_widevine_decryption' => true]],
]);

it('uses one signing method at a time', function (): void {
    $options = ShakaOptions::make()->rsaSigning('signer', '/keys/rsa.pem')->aesSigning('signer', 'aabb', 'ccdd');

    expect($options->toArray())->toBe(['signer' => 'signer', 'aes_signing_key' => 'aabb', 'aes_signing_iv' => 'ccdd'])
        ->and($options->rsaSigning('signer', '/keys/rsa.pem')->toArray())->toBe(['signer' => 'signer', 'rsa_signing_key_path' => '/keys/rsa.pem']);
});

it('rejects invalid values', function (Closure $configure, string $message): void {
    expect(fn (): ShakaOptions => $configure(ShakaOptions::make()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty base url' => [fn (ShakaOptions $o): ShakaOptions => $o->baseUrls(['']), 'base URLs must not be empty'],
    'negative buffer' => [fn (ShakaOptions $o): ShakaOptions => $o->timeShiftBufferDepth(-1), "can't be negative"],
    'negative segment number' => [fn (ShakaOptions $o): ShakaOptions => $o->startSegmentNumber(-1), 'at least 0'],
    'no protection system' => [fn (ShakaOptions $o): ShakaOptions => $o->protectionSystems(), 'at least one protection system'],
    'non-hex content id' => [fn (ShakaOptions $o): ShakaOptions => $o->widevine('https://license.test/', 'xyz'), 'content ID must be hexadecimal'],
    'zero pixels' => [fn (ShakaOptions $o): ShakaOptions => $o->maxPixels(sd: 0), 'at least 1'],
    'empty utc timings' => [fn (ShakaOptions $o): ShakaOptions => $o->utcTimings([]), 'must not be empty'],
]);
