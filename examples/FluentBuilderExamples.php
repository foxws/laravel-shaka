<?php

declare(strict_types=1);

namespace Foxws\Shaka\Examples;

use Foxws\Shaka\Facades\Shaka;
use Foxws\Shaka\Support\Packager\Packager;

/**
 * Examples using the fluent CommandBuilder API
 */
class FluentBuilderExamples
{
    /**
     * Example 1: Simple fluent API
     */
    public function simpleFluentApi(): void
    {
        $result = Shaka::open('input.mp4')
            ->addVideoStream('input.mp4', 'video.mp4')
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->execute();
    }

    /**
     * Example 2: Adaptive bitrate streaming
     */
    public function adaptiveBitrateStreaming(): void
    {
        $result = Shaka::open('input.mp4')
            ->addVideoStream('input.mp4', 'video_1080p.mp4', [
                'bandwidth' => '5000000',
            ])
            ->addVideoStream('input.mp4', 'video_720p.mp4', [
                'bandwidth' => '3000000',
            ])
            ->addVideoStream('input.mp4', 'video_480p.mp4', [
                'bandwidth' => '1500000',
            ])
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(6)
            ->execute();
    }

    /**
     * Example 3: HLS with encryption
     */
    public function hlsWithEncryption(): void
    {
        $result = Shaka::open('input.mp4')
            ->addVideoStream('input.mp4', 'video.m3u8')
            ->addAudioStream('input.mp4', 'audio.m3u8')
            ->withHlsMasterPlaylist('master.m3u8')
            ->withEncryption([
                'keys' => 'label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef',
                'key_server_url' => 'https://example.com/license',
            ])
            ->withSegmentDuration(6)
            ->execute();
    }

    /**
     * Example 4: Multiple input files
     */
    public function multipleInputFiles(): void
    {
        $result = Shaka::open(['input1.mp4', 'input2.mp4', 'input3.mp4'])
            ->addVideoStream('input1.mp4', 'video_1.mp4')
            ->addAudioStream('input1.mp4', 'audio_1.mp4')
            ->addVideoStream('input2.mp4', 'video_2.mp4')
            ->addAudioStream('input2.mp4', 'audio_2.mp4')
            ->addVideoStream('input3.mp4', 'video_3.mp4')
            ->addAudioStream('input3.mp4', 'audio_3.mp4')
            ->withMpdOutput('manifest.mpd')
            ->execute();
    }

    /**
     * Example 5: Using Packager directly with MediaCollection
     */
    public function usingPackagerDirectly(Packager $packager): void
    {
        $mediaCollection = \Foxws\Shaka\Support\Filesystem\MediaCollection::make([
            \Foxws\Shaka\Support\Filesystem\Media::make('videos', 'input.mp4'),
        ]);

        $result = $packager
            ->open($mediaCollection)
            ->addVideoStream('input.mp4', 'video.mp4', ['bandwidth' => '5000000'])
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(4)
            ->execute();
    }

    /**
     * Example 6: Building complex streams with custom options
     */
    public function complexStreamConfiguration(): void
    {
        $result = Shaka::open('input.mp4')
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'video',
                'output' => 'video_4k.mp4',
                'bandwidth' => '10000000',
                'resolution' => '3840x2160',
            ])
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'video',
                'output' => 'video_1080p.mp4',
                'bandwidth' => '5000000',
                'resolution' => '1920x1080',
            ])
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'audio',
                'output' => 'audio_en.mp4',
                'language' => 'en',
            ])
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'audio',
                'output' => 'audio_es.mp4',
                'language' => 'es',
            ])
            ->withMpdOutput('manifest.mpd')
            ->execute();
    }

    /**
     * Example 7: Accessing the builder for advanced configuration
     */
    public function advancedBuilderAccess(): void
    {
        $shaka = Shaka::open('input.mp4');

        // Access the builder directly for advanced configuration
        $builder = $shaka->builder();

        // Add streams
        $builder->addVideoStream('input.mp4', 'video.mp4');
        $builder->addAudioStream('input.mp4', 'audio.mp4');

        // Configure options
        $builder->withMpdOutput('manifest.mpd');
        $builder->withSegmentDuration(6);

        // Execute
        $result = $shaka->execute();
    }

    /**
     * Example 8: Error handling with fluent API
     */
    public function fluentWithErrorHandling(): void
    {
        try {
            $result = Shaka::open('input.mp4')
                ->addVideoStream('input.mp4', 'video.mp4')
                ->addAudioStream('input.mp4', 'audio.mp4')
                ->withMpdOutput('manifest.mpd')
                ->execute();

            logger()->info('Packaging successful', $result->toArray());
        } catch (\Foxws\Shaka\Exceptions\RuntimeException $e) {
            logger()->error('Packaging failed', ['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            logger()->error('No streams configured', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Example 9: Mixing different stream types
     */
    public function mixedStreamTypes(): void
    {
        $result = Shaka::open('input.mp4')
            // High quality video
            ->addVideoStream('input.mp4', 'video_high.mp4', [
                'bandwidth' => '8000000',
            ])
            // Medium quality video
            ->addVideoStream('input.mp4', 'video_medium.mp4', [
                'bandwidth' => '4000000',
            ])
            // Low quality video
            ->addVideoStream('input.mp4', 'video_low.mp4', [
                'bandwidth' => '2000000',
            ])
            // High quality audio
            ->addAudioStream('input.mp4', 'audio_high.mp4', [
                'bitrate' => '192000',
            ])
            // Low quality audio
            ->addAudioStream('input.mp4', 'audio_low.mp4', [
                'bitrate' => '96000',
            ])
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(4)
            ->execute();
    }

    /**
     * Example 10: Reusing the packager with different configurations
     */
    public function reusingPackager(Packager $packager): void
    {
        $mediaCollection = \Foxws\Shaka\Support\Filesystem\MediaCollection::make([
            \Foxws\Shaka\Support\Filesystem\Media::make('videos', 'input.mp4'),
        ]);

        // First packaging operation
        $result1 = $packager
            ->open($mediaCollection)
            ->addVideoStream('input.mp4', 'output1/video.mp4')
            ->withMpdOutput('output1/manifest.mpd')
            ->execute();

        // Get a fresh packager instance for the next operation
        $freshPackager = $packager->fresh();

        // Second packaging operation with different configuration
        $result2 = $freshPackager
            ->open($mediaCollection)
            ->addVideoStream('input.mp4', 'output2/video.mp4')
            ->addAudioStream('input.mp4', 'output2/audio.mp4')
            ->withHlsMasterPlaylist('output2/master.m3u8')
            ->execute();
    }
}
