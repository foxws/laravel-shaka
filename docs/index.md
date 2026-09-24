---
title: Introduction
metadata:
  role: Media
  eyebrow: "Video · HLS/DASH · Shaka Packager"
  desc: "Package video into HLS and DASH streams with a fluent Laravel API."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  runtime: "Shaka Packager"
  licence: MIT
  used_by:
    name: Stry
    desc: "A self-hosted video streaming app."
    href: "https://github.com/francoism90/stry"
---

# Introduction

This package runs [Shaka Packager](https://github.com/shaka-project/shaka-packager) from Laravel. It turns video and audio files into HLS and DASH streams, reads the source from any Laravel disk, and writes the result to any disk.

```php
use Foxws\Shaka\Facades\Shaka;

Shaka::fromDisk('media')
    ->open('videos/clip.mp4')
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->toDisk('s3')
    ->toPath('streams/clip/')
    ->save();
```

## What it does, and what it doesn't

Shaka Packager **packages**. It cuts already-encoded video into segments and writes the playlists that players read. It does not re-encode, so it's fast, and the output is about the same size as the input.

It can't make a 720p version out of a 1080p file. If you need several qualities, either encode them first (for example with FFmpeg) and add each file as a stream, or use [Laravel Streamer](https://github.com/foxws/laravel-streamer), which encodes and packages in one step.

## Features

- Read input from, and write output to, any Laravel disk: local, S3 or your own.
- Build DASH and HLS from the same segments in one run.
- Encrypt with AES and a generated key.
- Serve private streams by signing every URL in a playlist when it's requested.
- Upload to S3 in parallel, with multipart uploads for large files.

## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- The Shaka Packager binary

## Pages

- [Installation](installation.md)
- [Usage](usage.md)
- [URL Resolvers](url-resolvers.md)
- [Queues](queue-integration.md)
- [Encryption](aes-encryption.md)
- [Configuration](configuration.md)
- [Quick Reference](quick-reference.md)
- [How It Works](architecture.md)
- [Troubleshooting](troubleshooting.md)
