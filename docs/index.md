---
title: Introduction
metadata:
  role: Media
  eyebrow: "Video · HLS/DASH · Shaka Packager"
  desc: "Package adaptive streaming video (HLS, DASH) with a fluent Laravel API."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
---

# Introduction

Laravel Shaka connects your Laravel app to [Google's Shaka Packager](https://github.com/shaka-project/shaka-packager). It turns a video file into adaptive streaming formats — HLS and DASH — using a simple, chainable API that feels like the rest of Laravel.

```php
use Foxws\Shaka\Facades\Shaka;

$result = Shaka::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream('videos/input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withSegmentDuration(6)
    ->export()
    ->toDisk('export')
    ->save();
```

## Features

- **Fluent API** - Chain methods together, the same way you'd chain an Eloquent query.
- **Multiple disks** - Read source files from, and write output to, local disk, S3, or any Laravel filesystem disk.
- **Adaptive bitrate** - Produce several quality levels from one video so players can switch between them.
- **Encryption & DRM** - Built-in support for protecting your content.
- **HLS & DASH** - Both manifest formats are built from the same packaged segments in one pass, so there's no extra encoding step.
- **Testable** - The package is split into small, mockable pieces, so your own tests stay fast.
- **Type-safe** - Fully typed for PHP 8.3+.

## See also

- [Installation](./installation.md) - Get the package and Shaka Packager binary set up
- [Usage](./usage.md) - Walk through the core API
- [Quick Reference](./quick-reference.md) - Complete API reference
- [Configuration](./configuration.md) - Configuring the package
- [Architecture](./architecture.md) - Understanding the driver-based design
- [AES Encryption](./aes-encryption.md) - Encryption with key rotation
- [URL Resolvers](./url-resolvers.md) - Dynamic URL customization for CDN/signed URLs
- [Queue Integration](./queue-integration.md) - Process media in background queues
- [Troubleshooting](./troubleshooting.md) - Common issues and solutions
