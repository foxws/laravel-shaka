# Test Fixtures

This directory contains video fixtures used for testing the Laravel Shaka package.

## Generating Fixtures

To generate test video files with different codecs, run the provided script:

```bash
cd tests/Fixtures
bash generate-fixtures.sh
```

### Requirements

- **FFmpeg** with the following encoder support:
    - `libx264` - For H.264/AVC videos
    - `libx265` - For HEVC/H.265 videos
    - `libaom-av1` or `libsvtav1` - For AV1 videos (optional)

You can check available encoders with:

```bash
ffmpeg -encoders | grep -E "libx264|libx265|libaom|libsvtav1"
```

### Generated Files

The script generates the following test videos:

- **sample_h264.mp4** - H.264/AVC encoded video (most compatible)
- **sample_hevc.mp4** - HEVC/H.265 encoded video (high efficiency)
- **sample_av1.mp4** - AV1 encoded video (modern, royalty-free)

All videos are:

- 5 seconds duration
- 1280x720 resolution
- 30 fps
- Test pattern video with 1kHz sine wave audio
- ~100-200 KB in size

### Manual Generation

If you need to customize the settings, you can manually generate fixtures:

#### H.264/AVC

```bash
ffmpeg -f lavfi -i testsrc=duration=5:size=1280x720:rate=30 \
       -f lavfi -i sine=frequency=1000:duration=5:sample_rate=44100 \
       -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       sample_h264.mp4
```

#### HEVC/H.265

```bash
ffmpeg -f lavfi -i testsrc=duration=5:size=1280x720:rate=30 \
       -f lavfi -i sine=frequency=1000:duration=5:sample_rate=44100 \
       -c:v libx265 -preset medium -crf 28 -pix_fmt yuv420p \
       -tag:v hvc1 \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       sample_hevc.mp4
```

#### AV1 (with libaom-av1)

```bash
ffmpeg -f lavfi -i testsrc=duration=5:size=1280x720:rate=30 \
       -f lavfi -i sine=frequency=1000:duration=5:sample_rate=44100 \
       -c:v libaom-av1 -cpu-used 8 -crf 35 -pix_fmt yuv420p \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       -strict experimental \
       sample_av1.mp4
```

#### AV1 (with libsvtav1 - faster)

```bash
ffmpeg -f lavfi -i testsrc=duration=5:size=1280x720:rate=30 \
       -f lavfi -i sine=frequency=1000:duration=5:sample_rate=44100 \
       -c:v libsvtav1 -preset 8 -crf 35 -pix_fmt yuv420p \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       sample_av1.mp4
```

## Using Fixtures in Tests

Access fixtures in your tests using the `fixture()` helper function (provided by Pest):

```php
// Load fixture content
$videoContent = file_get_contents(fixture('sample_h264.mp4'));

// Put into Laravel Storage for testing
Storage::disk('local')->put('video.mp4', $videoContent);

// Or get the full path
$fixturePath = fixture('sample_hevc.mp4'); // returns: tests/Fixtures/sample_hevc.mp4
```

## Git Tracking

By default, generated fixture files are tracked in git (see `.gitignore`). This ensures consistent test data across all environments without requiring FFmpeg during CI/CD.

The following files are tracked:

- `sample_h264.mp4` (default fixture for H.264/AVC)
- `sample_hevc.mp4`
- `sample_av1.mp4`
- `generate-fixtures.sh`

All other files are ignored.
