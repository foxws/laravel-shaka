<?php

/**
 * Test script to validate encryption key detection for both HLS and DASH
 * This ensures the detection logic works with various key filename patterns
 */

// Test cases for key detection
$testCases = [
    // HLS rotation keys (no extension)
    ['filename' => 'key_0', 'expected' => true, 'type' => 'HLS rotation (no ext)'],
    ['filename' => 'key_1', 'expected' => true, 'type' => 'HLS rotation (no ext)'],
    ['filename' => 'key_99', 'expected' => true, 'type' => 'HLS rotation (no ext)'],

    // HLS rotation keys (with .key extension)
    ['filename' => 'key_0.key', 'expected' => true, 'type' => 'HLS rotation (.key ext)'],
    ['filename' => 'encryption_1.key', 'expected' => true, 'type' => 'HLS rotation (.key ext)'],
    ['filename' => 'drm_2.key', 'expected' => true, 'type' => 'HLS rotation (drm .key ext)'],
    ['filename' => 'aes_3.key', 'expected' => true, 'type' => 'HLS rotation (aes .key ext)'],

    // DASH rotation keys (no extension)
    ['filename' => 'encryption_0', 'expected' => true, 'type' => 'DASH rotation (no ext)'],
    ['filename' => 'drm_1', 'expected' => true, 'type' => 'DASH rotation (no ext)'],
    ['filename' => 'secret_5', 'expected' => true, 'type' => 'DASH rotation (secret)'],

    // Static keys
    ['filename' => 'key', 'expected' => true, 'type' => 'Static key'],
    ['filename' => 'encryption', 'expected' => true, 'type' => 'Static encryption'],
    ['filename' => 'drm', 'expected' => true, 'type' => 'Static drm'],
    ['filename' => 'secret', 'expected' => true, 'type' => 'Static secret'],
    ['filename' => 'aes', 'expected' => true, 'type' => 'Static aes'],

    // NOT keys - should be false
    ['filename' => 'master.m3u8', 'expected' => false, 'type' => 'HLS manifest'],
    ['filename' => 'manifest.mpd', 'expected' => false, 'type' => 'DASH manifest'],
    ['filename' => 'video_1080.m4s', 'expected' => false, 'type' => 'Video segment (quality)'],
    ['filename' => 'audio_128.m4s', 'expected' => false, 'type' => 'Audio segment (bitrate)'],
    ['filename' => 'init_0.mp4', 'expected' => false, 'type' => 'Init segment'],
    ['filename' => 'segment_0.ts', 'expected' => false, 'type' => 'TS segment'],
    ['filename' => 'chunk_0.m4s', 'expected' => false, 'type' => 'Media chunk'],
    ['filename' => 'custom_2.key', 'expected' => false, 'type' => 'Custom name not in allowlist'],
    ['filename' => 'video_1080', 'expected' => false, 'type' => 'Video (no ext, but not key name)'],
];

echo "Testing Encryption Key Detection\n";
echo str_repeat('=', 80)."\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    $filename = $test['filename'];
    $expected = $test['expected'];
    $type = $test['type'];

    // Apply the detection logic
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $basename = pathinfo($filename, PATHINFO_FILENAME);

    // Rotation key pattern: common key base names with _digits suffix
    $isRotationKey = preg_match('/^(key|encryption|drm|secret|aes)_\d+$/i', $basename);

    // Static key pattern: common key filenames without numeric suffix and no extension
    $isStaticKey = ! $extension && preg_match('/^(key|encryption|drm|secret|aes)$/i', $filename);

    $isKeyFile = $isRotationKey || $isStaticKey;

    $result = $isKeyFile === $expected ? '✓ PASS' : '✗ FAIL';
    $status = $isKeyFile === $expected ? 'passed' : 'failed';

    if ($status === 'passed') {
        $passed++;
        echo sprintf("%-15s | %-35s | %s\n", $result, $filename, $type);
    } else {
        $failed++;
        echo sprintf("%-15s | %-35s | %s (expected: %s, got: %s)\n",
            $result, $filename, $type,
            $expected ? 'true' : 'false',
            $isKeyFile ? 'true' : 'false'
        );
    }
}

echo "\n".str_repeat('=', 80)."\n";
echo sprintf("Results: %d passed, %d failed out of %d tests\n", $passed, $failed, count($testCases));

if ($failed > 0) {
    exit(1);
}

echo "\n✓ All encryption key detection tests passed!\n";
echo "✓ Works correctly for both HLS and DASH\n";
echo "✓ Handles rotation keys with various naming patterns\n";
echo "✓ Properly excludes non-key files (manifests, segments)\n";
