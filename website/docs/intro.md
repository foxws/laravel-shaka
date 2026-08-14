---
sidebar_position: 1
---

# Introduction

A Laravel integration for [Google's Shaka Packager](https://github.com/shaka-project/shaka-packager), enabling you to create adaptive streaming content (HLS, DASH) with a fluent, Laravel-style API.

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

- **Fluent API** - Laravel-style chainable methods
- **Multiple disks** - Works with local, S3, and custom filesystems
- **Adaptive bitrate** - Create multi-quality streams easily
- **Encryption & DRM** - Built-in support for content protection
- **HLS & DASH** - Support for both streaming protocols
- **Testable** - Clean architecture with mockable components
- **Type-safe** - Full PHP 8.1+ type declarations

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
