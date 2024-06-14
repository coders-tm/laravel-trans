<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->langPath = base_path('resources/lang');
    if (! file_exists($this->langPath)) {
        mkdir($this->langPath, 0755, true);
    }
});

afterEach(function () {
    $files = glob($this->langPath.'/*.json');
    foreach ($files as $file) {
        unlink($file);
    }
    $csvPath = $this->langPath.'/translations.csv';
    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
});

it('exports translations to CSV', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Hello' => 'Hello',
        'Goodbye' => 'Goodbye',
    ], JSON_PRETTY_PRINT));

    File::put($this->langPath.'/es.json', json_encode([
        'Hello' => 'Hola',
        'Goodbye' => 'Adios',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:export-csv')
        ->assertExitCode(0);

    $csvPath = $this->langPath.'/translations.csv';
    expect(file_exists($csvPath))->toBeTrue();

    $content = File::get($csvPath);
    expect($content)->toContain('key,en,es');
    expect($content)->toContain('Hello');
    expect($content)->toContain('Goodbye');
});

it('exports only en keys as master', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Only In En' => 'Only In En',
    ], JSON_PRETTY_PRINT));

    File::put($this->langPath.'/es.json', json_encode([
        'Only In Es' => 'Solo en Es',
        'Only In En' => 'Solo en Es',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:export-csv')
        ->assertExitCode(0);

    $csvPath = $this->langPath.'/translations.csv';
    $content = File::get($csvPath);
    expect($content)->toContain('Only In En');
    expect($content)->not->toContain('Only In Es');
});
