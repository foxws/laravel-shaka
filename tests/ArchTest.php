<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('classes in src/Support extend nothing or base classes')
    ->expect('Foxws\\Shaka\\Support')
    ->classes()
    ->not->toExtend('Illuminate\\Support\\Facades\\Facade');

arch('exceptions extend base exception classes')
    ->expect('Foxws\\Shaka\\Exceptions')
    ->toExtend(Exception::class);

arch('facades extend Facade')
    ->expect('Foxws\\Shaka\\Facades')
    ->toExtend('Illuminate\\Support\\Facades\\Facade');

arch('classes use strict types')
    ->expect('Foxws\\Shaka')
    ->toUseStrictTypes();
