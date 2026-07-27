<?php

declare(strict_types=1);

use Foxws\Shaka\Support\CommandBuilder;

it('sets a single base URL for the DASH MPD', function () {
    $builder = CommandBuilder::make()->withBaseUrls('https://cdn.example.com/');

    expect($builder->getOptions()['base_urls'])->toBe('https://cdn.example.com/');
});

it('joins multiple base URLs with commas', function () {
    $builder = CommandBuilder::make()->withBaseUrls([
        'https://cdn1.example.com/',
        'https://cdn2.example.com/',
    ]);

    expect($builder->getOptions()['base_urls'])->toBe('https://cdn1.example.com/,https://cdn2.example.com/');
});

it('rejects an empty base URL', function () {
    CommandBuilder::make()->withBaseUrls('');
})->throws(InvalidArgumentException::class, 'Base URLs must not be empty');

it('rejects an empty array of base URLs', function () {
    CommandBuilder::make()->withBaseUrls([]);
})->throws(InvalidArgumentException::class, 'Base URLs must not be empty');

it('rejects an array containing an empty base URL', function () {
    CommandBuilder::make()->withBaseUrls(['https://cdn.example.com/', '']);
})->throws(InvalidArgumentException::class, 'Base URLs must not be empty');
