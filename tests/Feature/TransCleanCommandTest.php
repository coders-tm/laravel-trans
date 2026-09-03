<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::put(
        base_path('resources/views/translations.blade.php'),
        '<p>{{ __("Hello World") }}</p><button>{{ __("Submit Form") }}</button>'
    );
});

afterEach(function () {
    @unlink(base_path('resources/views/translations.blade.php'));
    foreach (glob(base_path('resources/lang/*.json')) as $file) {
        @unlink($file);
    }
});

it('removes unused keys from en.json', function () {
    $enPath = base_path('resources/lang/en.json');
    File::put($enPath, json_encode([
        'Hello World' => 'Hello World',
        'Submit Form' => 'Submit Form',
        'Unused Key' => 'Unused Key',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:clean')
        ->expectsConfirmation('Are you sure you want to delete these keys? This will update your language files.', 'yes')
        ->assertExitCode(0);

    $translations = json_decode(File::get($enPath), true);
    expect($translations)->toHaveKey('Hello World');
    expect($translations)->not->toHaveKey('Unused Key');
});

it('dry-run does not modify files', function () {
    $enPath = base_path('resources/lang/en.json');
    File::put($enPath, json_encode([
        'Hello World' => 'Hello World',
        'Unused Key' => 'Unused Key',
    ], JSON_PRETTY_PRINT));

    $originalContent = File::get($enPath);

    $this->artisan('trans:clean', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(File::get($enPath))->toBe($originalContent);
});

it('cleans other locale files too', function () {
    $enPath = base_path('resources/lang/en.json');
    $xxPath = base_path('resources/lang/xx.json');

    File::put($enPath, json_encode([
        'Hello World' => 'Hello World',
        'Unused Key' => 'Unused Key',
    ], JSON_PRETTY_PRINT));

    File::put($xxPath, json_encode([
        'Hello World' => 'Hola Mundo',
        'Unused Key' => 'Clave No Usada',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:clean')
        ->expectsConfirmation('Are you sure you want to delete these keys? This will update your language files.', 'yes')
        ->assertExitCode(0);

    $xxTranslations = json_decode(File::get($xxPath), true);
    expect($xxTranslations)->toHaveKey('Hello World');
    expect($xxTranslations)->not->toHaveKey('Unused Key');
});

it('keeps all keys when everything is used', function () {
    $enPath = base_path('resources/lang/en.json');
    File::put($enPath, json_encode([
        'Hello World' => 'Hello World',
        'Submit Form' => 'Submit Form',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:clean')
        ->assertExitCode(0);

    $translations = json_decode(File::get($enPath), true);
    expect($translations)->toHaveKeys(['Hello World', 'Submit Form']);
});
