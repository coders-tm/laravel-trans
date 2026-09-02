<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    @unlink(base_path('resources/lang/en.json'));
    @unlink(base_path('resources/lang/es.json'));
    @unlink(base_path('resources/lang/translations.csv'));
});

it('imports translations from CSV', function () {
    $csvPath = base_path('resources/lang/translations.csv');
    $csvContent = "key,en,es\nHello,Hello,Hola\nGoodbye,Goodbye,Adios\n";
    File::put($csvPath, $csvContent);

    $this->artisan('trans:import-csv')
        ->assertExitCode(0);

    $enTranslations = json_decode(File::get(base_path('resources/lang/en.json')), true);
    expect($enTranslations)->toHaveKey('Hello');
    expect($enTranslations['Hello'])->toBe('Hello');

    $esTranslations = json_decode(File::get(base_path('resources/lang/es.json')), true);
    expect($esTranslations)->toHaveKey('Hello');
    expect($esTranslations['Hello'])->toBe('Hola');
});

it('merges CSV imports with existing JSON', function () {
    $csvPath = base_path('resources/lang/translations.csv');
    File::put(base_path('resources/lang/en.json'), json_encode([
        'Existing' => 'Already Here',
    ], JSON_PRETTY_PRINT));

    File::put($csvPath, "key,en\nNew Key,Brand New\n");

    $this->artisan('trans:import-csv')
        ->assertExitCode(0);

    $translations = json_decode(File::get(base_path('resources/lang/en.json')), true);
    expect($translations)->toHaveKeys(['Existing', 'New Key']);
    expect($translations['Existing'])->toBe('Already Here');
    expect($translations['New Key'])->toBe('Brand New');
});

it('fails gracefully when CSV does not exist', function () {
    $this->artisan('trans:import-csv')
        ->assertExitCode(1);
});

it('fails gracefully with invalid CSV format', function () {
    $csvPath = base_path('resources/lang/translations.csv');
    File::put($csvPath, "foo,bar,baz\n");

    $this->artisan('trans:import-csv')
        ->assertExitCode(1);
});
