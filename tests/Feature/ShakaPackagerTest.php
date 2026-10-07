<?php

declare(strict_types=1);

use Foxws\Media\Encryption\EncryptionKey;
use Foxws\Media\Encryption\ProtectionScheme;
use Foxws\Media\Events\ExportCompleted;
use Foxws\Media\Executables\Binary;
use Foxws\Media\Facades\Media;
use Foxws\Media\Filesystem\Media as MediaFile;
use Foxws\Media\Filesystem\TemporaryDirectories;
use Foxws\Media\Packaging\Encryption;
use Foxws\Media\Packaging\HlsPlaylistType;
use Foxws\Media\Packaging\PackagerManager;
use Foxws\Media\Packaging\PackagingSpec;
use Foxws\Media\Packaging\PackagingStream;
use Foxws\Media\Packaging\StreamType;
use Foxws\Media\Process\Result;
use Foxws\Media\Process\Runner;
use Foxws\Shaka\ShakaExecutable;
use Foxws\Shaka\ShakaOptions;
use Foxws\Shaka\ShakaPackager;
use Foxws\Shaka\Testing\FakeShaka;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('videos');
    Storage::fake('streams');

    $this->fake = FakeShaka::respond(Media::fake());
});

function shakaMedia(string $path): MediaFile
{
    Storage::disk('videos')->put($path, 'video');

    return Media::fromDisk('videos')->open($path)->mediaFor();
}

function shaka(): ShakaPackager
{
    return app(PackagerManager::class)->driver('shaka');
}

it('describes each stream and the manifests as shaka packager arguments', function (): void {
    $media = shakaMedia('video.mp4');

    $spec = new PackagingSpec(
        streams: [
            new PackagingStream(StreamType::Video, $media, '0_video.mp4'),
            new PackagingStream(StreamType::Audio, $media, '0_audio.mp4', language: 'eng'),
            new PackagingStream(StreamType::Text, $media, 'captions/nld.mp4', language: 'nld', options: ['dash_roles' => 'subtitle']),
        ],
        dashManifest: 'manifest.mpd',
        hlsPlaylist: 'master.m3u8',
        hlsPlaylistType: HlsPlaylistType::Vod,
        segmentDuration: 6,
        fragmentDuration: 2,
        defaultLanguage: 'eng',
        defaultTextLanguage: 'nld',
        allowCodecSwitching: true,
        approximateSegmentTimeline: true,
        options: ['hls_base_url' => 'https://cdn.test/', 'generate_static_live_mpd' => false],
    );

    expect(shaka()->arguments($spec, '/tmp/out'))->toBe([
        'in=video.mp4,stream=video,output=/tmp/out/0_video.mp4',
        'in=video.mp4,stream=audio,output=/tmp/out/0_audio.mp4,language=eng',
        'in=video.mp4,stream=text,output=/tmp/out/captions/nld.mp4,language=nld,dash_roles=subtitle',
        '--mpd_output=/tmp/out/manifest.mpd',
        '--hls_master_playlist_output=/tmp/out/master.m3u8',
        '--hls_playlist_type=VOD',
        '--segment_duration=6',
        '--fragment_duration=2',
        '--default_language=eng',
        '--default_text_language=nld',
        '--allow_codec_switching',
        '--allow_approximate_segment_timeline',
        '--hls_base_url=https://cdn.test/',
        '--quiet',
    ]);
});

it('reads local copies of the inputs when packaging, linking unsafe names under a plain one', function (): void {
    $safe = shakaMedia('safe.mp4');
    $unsafe = shakaMedia('my video, part 1.mp4');
    $inputs = app(TemporaryDirectories::class)->createCache();

    $arguments = shaka()->arguments(new PackagingSpec([
        new PackagingStream(StreamType::Video, $safe, 'a.mp4'),
        new PackagingStream(StreamType::Video, $unsafe, 'b.mp4'),
    ]), '/tmp/out', $inputs);

    expect($arguments[0])->toStartWith('in='.str_replace('\\', '/', Storage::disk('videos')->path('safe.mp4')).',')
        ->and($arguments[1])->toStartWith('in='.$inputs->path('input-1.mp4').',')
        ->and(file_get_contents($inputs->path('input-1.mp4')))->toBe('video');
});

it('keeps descriptor fields from being split or read as options', function (): void {
    $arguments = shaka()->arguments(new PackagingSpec([
        new PackagingStream(StreamType::Audio, shakaMedia('video.mp4'), 'audio.mp4', options: ['hls_name' => 'English, “Original”', 'dash_label' => '-label']),
    ]), '/tmp/out');

    expect($arguments[0])->toEndWith(',hls_name=English- "Original,dash_label=./-label');
});

it('rejects option names that could inject arguments', function (): void {
    shaka()->arguments(new PackagingSpec([new PackagingStream(StreamType::Video, shakaMedia('video.mp4'), 'video.mp4')], options: ['mpd_output=/etc/passwd --x' => true]), '/tmp/out');
})->throws(InvalidArgumentException::class, 'Invalid Shaka Packager option or field name');

it('rejects fragments longer than segments', function (): void {
    shaka()->arguments(new PackagingSpec([new PackagingStream(StreamType::Video, shakaMedia('video.mp4'), 'video.mp4')], segmentDuration: 2, fragmentDuration: 4), '/tmp/out');
})->throws(InvalidArgumentException::class, "The fragment duration (4s) can't be longer than the segment duration (2s).");

it('encrypts with a raw key and keeps the key out of the reported command', function (): void {
    $key = new EncryptionKey('0123456789abcdef0123456789abcdef', 'fedcba9876543210fedcba9876543210');
    $spec = new PackagingSpec(
        [new PackagingStream(StreamType::Video, shakaMedia('video.mp4'), 'video.mp4')],
        hlsPlaylist: 'master.m3u8',
        encryption: new Encryption($key, ProtectionScheme::Cbcs, rotation: 300, clearLead: 2.5, label: 'SD'),
    );

    expect(array_slice(shaka()->arguments($spec, '/tmp/out'), 1))->toBe([
        '--hls_master_playlist_output=/tmp/out/master.m3u8',
        '--enable_raw_key_encryption',
        '--keys=label=SD:key_id=fedcba9876543210fedcba9876543210:key=0123456789abcdef0123456789abcdef',
        '--protection_scheme=cbcs',
        '--clear_lead=2.5',
        '--hls_key_uri=key',
        '--crypto_period_duration=300',
        '--quiet',
    ])->and(shaka()->command($spec, '/tmp/out'))
        ->toContain('--keys=[REDACTED]')
        ->not->toContain('0123456789abcdef0123456789abcdef');
});

it('packages with shaka(), saves the outputs to the target disk and removes the linked inputs', function (): void {
    Event::fake([ExportCompleted::class]);
    Storage::disk('videos')->put('my clip.mp4', 'video');

    $result = Media::fromDisk('videos')->open('my clip.mp4')
        ->shaka()
        ->addVideoStream(output: 'video.mp4')
        ->addAudioStream(output: 'audio.mp4', language: 'eng')
        ->withHlsPlaylist()
        ->withDashManifest()
        ->toDisk('streams')
        ->timeout(300)
        ->save('clip');

    $arguments = $this->fake->commands(ShakaExecutable::Packager)[0];

    expect($result->paths())->toBe(['clip/master.m3u8', 'clip/manifest.mpd', 'clip/audio.mp4', 'clip/video.mp4'])
        ->and(file_exists(substr(explode(',', $arguments[0])[0], 3)))->toBeFalse();
    $this->fake->assertSaved('clip/master.m3u8', 'streams');
    Event::assertDispatched(ExportCompleted::class);
});

it('runs within the configured timeout unless the builder passes one', function (?int $timeout, int $expected): void {
    config(['shaka.timeout' => 120]);
    $runner = Mockery::mock(Runner::class);
    $runner->shouldReceive('run')
        ->once()
        ->withArgs(fn (Binary $executable, array $arguments, ?int $runTimeout): bool => $executable === ShakaExecutable::Packager && $runTimeout === $expected)
        ->andReturn(new Result(ShakaExecutable::Packager, '', 0, '', '', 0.0));
    app()->instance(Runner::class, $runner);

    shaka()->package(new PackagingSpec([new PackagingStream(StreamType::Video, shakaMedia('video.mp4'), 'video.mp4')]), app(TemporaryDirectories::class)->create(), $timeout);
})->with([
    'configured' => [null, 120],
    'builder' => [300, 300],
]);

it('passes typed options to the packager', function (): void {
    Storage::disk('videos')->put('clip.mp4', 'video');

    Media::fromDisk('videos')->open('clip.mp4')
        ->shaka()
        ->addVideoStream()
        ->withDashManifest()
        ->withOptions(ShakaOptions::make()->baseUrls('https://cdn.test/')->lowLatencyDashMode())
        ->save('clip');

    $this->fake->assertRan(ShakaExecutable::Packager, fn (array $arguments): bool => in_array('--base_urls=https://cdn.test/', $arguments, true)
        && in_array('--low_latency_dash_mode', $arguments, true));
});
