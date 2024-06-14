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

it('imports translations from CSV', function () {
    $csvContent = "key,en,es\nHello,Hello,Hola\nGoodbye,Goodbye,Adios\n";
    File::put($this->langPath.'/translations.csv', $csvContent);

    $this->artisan('trans:import-csv')
        ->assertExitCode(0);

    $enTranslations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($enTranslations)->toHaveKeys(['Hello', 'Goodbye']);
    expect($enTranslations['Hello'])->toBe('Hello');

    $esTranslations = json_decode(File::get($this->langPath.'/es.json'), true);
    expect($esTranslations['Hello'])->toBe('Hola');
    expect($esTranslations['Goodbye'])->toBe('Adios');
});

it('merges CSV imports with existing JSON', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Existing' => 'Already Here',
    ], JSON_PRETTY_PRINT));

    $csvContent = "key,en\nNew Key,Brand New\n";
    File::put($this->langPath.'/translations.csv', $csvContent);

    $this->artisan('trans:import-csv')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveKeys(['Existing', 'New Key']);
    expect($translations['Existing'])->toBe('Already Here');
    expect($translations['New Key'])->toBe('Brand New');
});

it('fails gracefully when CSV does not exist', function () {
    $this->artisan('trans:import-csv')
        ->assertExitCode(1);
});

it('fails gracefully with invalid CSV format', function () {
    File::put($this->langPath.'/translations.csv', "foo,bar,baz\n");

    $this->artisan('trans:import-csv')
        ->assertExitCode(1);
});
