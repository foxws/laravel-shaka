<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring('Foxws\\Shaka\\Exporters\\MediaExporter');

arch('classes in src/Support extend nothing or base classes')
    ->expect('Foxws\\Shaka\\Support')
    ->classes()
    ->not->toExtend('Illuminate\\Support\\Facades\\Facade');
