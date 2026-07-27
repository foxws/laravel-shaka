<?php

declare(strict_types=1);

use Foxws\Shaka\Exceptions\InvalidStreamConfigurationException;
use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\Support\CommandBuilder;
use Foxws\Shaka\Support\EncryptionKey;
use Foxws\Shaka\Support\HlsPlaylistType;
use Foxws\Shaka\Support\ProtectionScheme;
use Foxws\Shaka\Support\SigningCredentials;
use Foxws\Shaka\Support\Stream;

// EncryptionKey

it('formats an encryption key for Shaka', function () {
    $key = new EncryptionKey('69eaa802a6763af979e8d1940fb88392', 'abba271e8bcf552bbd2e86a434a9a5d9');

    expect($key->toShakaFormat())->toBe('label=:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392')
        ->and($key->toShakaFormat('SD'))->toBe('label=SD:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392');
});

it('converts an encryption key to an array', function () {
    $key = new EncryptionKey('key456', 'keyid123', '/tmp/key');

    expect($key->toArray())->toBe([
        'key' => 'key456',
        'key_id' => 'keyid123',
        'file_path' => '/tmp/key',
    ]);
});

// Stream

it('builds a stream descriptor from a raw array', function () {
    $stream = Stream::fromArray([
        'in' => 'input.mp4',
        'stream' => 'video',
        'output' => 'output.mp4',
        'bandwidth' => '5000000',
    ]);

    expect($stream->getInput())->toBe('input.mp4')
        ->and($stream->getType())->toBe('video')
        ->and($stream->getOutput())->toBe('output.mp4')
        ->and($stream->getOptions())->toBe(['bandwidth' => '5000000'])
        ->and($stream->toDescriptorArray())->toBe([
            'in' => 'input.mp4',
            'stream' => 'video',
            'output' => 'output.mp4',
            'bandwidth' => '5000000',
        ]);
});

it('rejects a stream array missing the input field', function () {
    Stream::fromArray(['stream' => 'video', 'output' => 'out.mp4']);
})->throws(InvalidStreamConfigurationException::class, 'Stream configuration missing required field: in');

it('rejects a stream array missing the stream type field', function () {
    Stream::fromArray(['in' => 'in.mp4', 'output' => 'out.mp4']);
})->throws(InvalidStreamConfigurationException::class, 'Stream configuration missing required field: stream');

it('is immutable when adding options', function () {
    $media = mock(Media::class);

    $original = Stream::video($media);
    $withOption = $original->addOption('language', 'en');

    expect($original->getOptions())->toBe([])
        ->and($withOption->getOptions())->toBe(['language' => 'en'])
        ->and($original)->not->toBe($withOption);
});

it('rejects a stream with an invalid type via CommandBuilder', function () {
    CommandBuilder::make()->addStream(Stream::fromArray([
        'in' => 'in.mp4',
        'stream' => 'invalid',
        'output' => 'out.mp4',
    ]));
})->throws(InvalidStreamConfigurationException::class, 'Invalid stream type');

it('rejects a stream without an output via CommandBuilder', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getLocalPath')->andReturn('in.mp4');

    CommandBuilder::make()->addStream(Stream::video($media));
})->throws(InvalidStreamConfigurationException::class, 'Stream configuration missing required field: output');

it('accepts a Stream value object built from Media via CommandBuilder', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getLocalPath')->andReturn('/tmp/in.mp4');

    $stream = Stream::video($media)->setOutput('out.mp4');

    $builder = CommandBuilder::make()->addStream($stream);

    expect($builder->buildArray())->toBe(['in=/tmp/in.mp4,stream=video,output=out.mp4']);
});

// ProtectionScheme / HlsPlaylistType enums

it('accepts a ProtectionScheme enum directly', function () {
    $builder = CommandBuilder::make()->withProtectionScheme(ProtectionScheme::Cbcs);

    expect($builder->getOptions()['protection_scheme'])->toBe('cbcs');
});

it('rejects an invalid protection scheme string', function () {
    CommandBuilder::make()->withProtectionScheme('invalid');
})->throws(InvalidArgumentException::class, 'Protection scheme must be one of');

it('accepts an HlsPlaylistType enum directly', function () {
    $builder = CommandBuilder::make()->withHlsPlaylistType(HlsPlaylistType::Event);

    expect($builder->getOptions()['hls_playlist_type'])->toBe('EVENT');
});

it('rejects an invalid HLS playlist type string', function () {
    CommandBuilder::make()->withHlsPlaylistType('invalid');
})->throws(InvalidArgumentException::class, 'HLS playlist type must be one of');

// SigningCredentials

it('builds AES signing options', function () {
    $credentials = SigningCredentials::aes('aabbccdd', 'ddccbbaa');

    $builder = CommandBuilder::make()->withSigningCredentials($credentials);

    expect($builder->getOptions())->toBe([
        'aes_signing_key' => 'aabbccdd',
        'aes_signing_iv' => 'ddccbbaa',
    ]);
});

it('builds RSA signing options', function () {
    $credentials = SigningCredentials::rsa('/path/to/key.pem');

    $builder = CommandBuilder::make()->withSigningCredentials($credentials);

    expect($builder->getOptions())->toBe([
        'rsa_signing_key_path' => '/path/to/key.pem',
    ]);
});

it('rejects a non-hex AES signing key', function () {
    SigningCredentials::aes('not-hex!', 'ddccbbaa');
})->throws(InvalidArgumentException::class, 'AES signing key must be a non-empty hex string');

it('rejects an empty RSA signing key path', function () {
    SigningCredentials::rsa('');
})->throws(InvalidArgumentException::class, 'RSA signing key path must not be empty');
