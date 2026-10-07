---
title: Introduction
metadata:
  role: Media
  group: media
  eyebrow: "Video · HLS/DASH · Shaka Packager"
  desc: "Package video into HLS and DASH streams with Shaka Packager."
  lead: "Package already-encoded video into HLS and DASH with Shaka Packager, including DRM and live DASH. Read from any Laravel disk, and write to any disk."
  requires: "PHP ^8.4"
  laravel: "13.x"
  runtime: "Shaka Packager, foxws/laravel-media"
  licence: MIT
  used_by:
    - name: Stry
      desc: "A self-hosted video streaming app."
      href: "https://github.com/francoism90/stry"
---

# Introduction

This package runs [Shaka Packager](https://github.com/shaka-project/shaka-packager) from Laravel. It turns video and audio files into HLS and DASH streams.

It's an add-on for [foxws/laravel-media](https://github.com/foxws/laravel-media): it adds a `shaka` driver to laravel-media's packaging builder. You open media with laravel-media, and `shaka()` packages it:

```php
use Foxws\Media\Facades\Media;

Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->shaka()
    ->addStreamsFrom()
    ->withHlsPlaylist()
    ->withDashManifest()
    ->toDisk('s3')
    ->save('streams/clip');
```

## What it does, and what it doesn't

Shaka Packager **packages**. It cuts already-encoded video into segments and writes the playlists that players read. It does not re-encode, so it's fast, and the output is about the same size as the input.

To get several qualities, encode them first with laravel-media's rendition ladder, then add each file as a stream.

laravel-media's own `native` packager needs only FFmpeg. Use Shaka Packager when you need what the native packager doesn't do: `cbcs` encryption for Safari, key rotation, a clear lead, Widevine and PlayReady, live and low-latency DASH, or several audio streams.

## Features

- Every packaging feature of laravel-media: any Laravel disk for input and output, DASH and HLS from the same segments, AES encryption with a generated key, signed playlists with `DynamicHLSPlaylist` and `DynamicDASHManifest`, concurrent S3 uploads, events and temporary file cleanup.
- Shaka Packager's own options as typed methods with `ShakaOptions`: DRM systems, Widevine and PlayReady key servers, live DASH, base URLs and segment numbering.
- `Media::fake()` in tests, with `FakeShaka` writing placeholder outputs.

## Requirements

- PHP 8.4 or higher
- Laravel 13
- [foxws/laravel-media](https://github.com/foxws/laravel-media) 0.3.4 or higher
- The [Shaka Packager](https://github.com/shaka-project/shaka-packager/releases) binary

## Pages

- [Installation](installation.md)
- [Usage](usage.md)
- [Testing](testing.md)
- [Configuration](configuration.md)
- [Upgrading from 2.x](upgrading.md)
