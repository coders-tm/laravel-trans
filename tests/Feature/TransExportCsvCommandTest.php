<?php

use Illuminate\Support\Facades\File;

$originalEnContent = file_get_contents(dirname(__DIR__, 2).'/workbench/resources/lang/en.json');
$originalEsContent = file_get_contents(dirname(__DIR__, 2).'/workbench/resources/lang/es.json');

afterEach(function () use ($originalEnContent, $originalEsContent) {
    File::put(base_path('resources/lang/en.json'), $originalEnContent);
    File::put(base_path('resources/lang/es.json'), $originalEsContent);
    @unlink(base_path('resources/lang/translations.csv'));
});

it('exports translations to CSV', function () {
    $this->artisan('trans:export-csv')
        ->assertExitCode(0);

    $csvPath = base_path('resources/lang/translations.csv');
    expect(file_exists($csvPath))->toBeTrue();

    $content = File::get($csvPath);
    expect($content)->toContain('key,en,es');
    expect($content)->toContain('Accept');
});

it('exports only en keys as master', function () {
    File::put(base_path('resources/lang/en.json'), json_encode([
        'Only In En' => 'Only In En',
        'In Both' => 'In Both',
    ], JSON_PRETTY_PRINT));

    File::put(base_path('resources/lang/es.json'), json_encode([
        'In Both' => 'En Ambos',
        'Only In Es' => 'Solo En Es',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:export-csv')
        ->assertExitCode(0);

    $csvPath = base_path('resources/lang/translations.csv');
    $content = File::get($csvPath);
    expect($content)->toContain('Only In En');
    expect($content)->toContain('In Both');
    expect($content)->not->toContain('Only In Es');
});
