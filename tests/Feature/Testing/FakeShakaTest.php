<?php

declare(strict_types=1);

use Foxws\Media\Facades\Media;
use Foxws\Shaka\Testing\FakeShaka;
use Illuminate\Support\Facades\Storage;

it('writes placeholders for every stream output and manifest', function (): void {
    Storage::fake('media');
    Storage::disk('media')->put('clip.mp4', 'video');
    $fake = FakeShaka::respond(Media::fake());

    Media::fromDisk('media')->open('clip.mp4')
        ->shaka()
        ->addVideoStream(output: 'video/0.mp4')
        ->withHlsPlaylist('hls/master.m3u8')
        ->withDashManifest('dash/manifest.mpd')
        ->save('out');

    $fake->assertSaved('out/video/0.mp4', 'media');
    $fake->assertSaved('out/hls/master.m3u8', 'media');
    $fake->assertSaved('out/dash/manifest.mpd', 'media');
    expect(Storage::disk('media')->get('out/video/0.mp4'))->toBe('fake media');
});
