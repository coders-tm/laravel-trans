<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    @unlink(base_path('resources/lang/en.json'));
    @unlink(base_path('resources/lang/es.json'));
    @unlink(base_path('resources/lang/translations.csv'));
});

it('exports translations to CSV', function () {
    $langPath = base_path('resources/lang');

    File::put($langPath.'/en.json', json_encode([
        'Accept' => 'Accept',
        'Hello :name' => 'Hello :name',
    ], JSON_PRETTY_PRINT));

    File::put($langPath.'/es.json', json_encode([
        'Accept' => 'Aceptar',
        'Hello :name' => 'Hola :name',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:export-csv')
        ->assertExitCode(0);

    $csvPath = $langPath.'/translations.csv';
    expect(file_exists($csvPath))->toBeTrue();

    $content = File::get($csvPath);
    expect($content)->toContain('key,en,es');
    expect($content)->toContain('Accept');
});

it('exports only en keys as master', function () {
    $langPath = base_path('resources/lang');

    File::put($langPath.'/en.json', json_encode([
        'Only In En' => 'Only In En',
        'In Both' => 'In Both',
    ], JSON_PRETTY_PRINT));

    File::put($langPath.'/es.json', json_encode([
        'In Both' => 'En Ambos',
        'Only In Es' => 'Solo En Es',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:export-csv')
        ->assertExitCode(0);

    $csvPath = $langPath.'/translations.csv';
    $content = File::get($csvPath);
    expect($content)->toContain('Only In En');
    expect($content)->toContain('In Both');
    expect($content)->not->toContain('Only In Es');
});
