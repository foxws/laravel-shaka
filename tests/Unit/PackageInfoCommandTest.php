<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

it('reports success when the Shaka Packager binary is executable', function () {
    Process::fake([
        '*' => Process::result(output: 'libpackager version 3.4.2 (abc123)', exitCode: 0),
    ]);

    $this->artisan('shaka:info')
        ->expectsOutputToContain('Shaka Packager is properly configured')
        ->assertExitCode(0);
});

it('reports failure when the Shaka Packager binary cannot be executed', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'command not found', exitCode: 127),
    ]);

    $this->artisan('shaka:info')
        ->expectsOutputToContain('Cannot execute Shaka Packager binary')
        ->assertExitCode(1);
});
