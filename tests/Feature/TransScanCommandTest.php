<?php

use Illuminate\Support\Facades\File;

$originalEn = [
    'Accept' => 'Accept',
    'Hello :name' => 'Hello :name',
    ':app_name © :year All Rights Reserved' => ':app_name © :year All Rights Reserved',
];

$originalEs = [
    'Accept' => 'Aceptar',
    'Hello :name' => 'Hola :name',
];

afterEach(function () use ($originalEn, $originalEs) {
    File::put(base_path('resources/lang/en.json'), json_encode($originalEn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    File::put(base_path('resources/lang/es.json'), json_encode($originalEs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @unlink(base_path('resources/lang/translations.csv'));
});

it('scans codebase and creates en.json with found keys', function () {
    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $enPath = base_path('resources/lang/en.json');
    expect(file_exists($enPath))->toBeTrue();

    $translations = json_decode(File::get($enPath), true);
    expect($translations)->toHaveKeys(['Hello World', 'Submit Form', 'Dashboard Title', 'Welcome Back']);
});

it('preserves existing translations in en.json', function () {
    $enPath = base_path('resources/lang/en.json');
    File::put($enPath, json_encode([
        'Hello :name' => 'Custom Value',
    ], JSON_PRETTY_PRINT));

    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get($enPath), true);
    expect($translations['Hello :name'])->toBe('Custom Value');
    expect($translations)->toHaveKey('Hello World');
});

it('dry-run does not modify files', function () {
    $enPath = base_path('resources/lang/en.json');
    $originalContent = File::get($enPath);

    $this->artisan('trans:scan', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(File::get($enPath))->toBe($originalContent);
});

it('normalizes placeholders in scanned keys', function () {
    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get(base_path('resources/lang/en.json')), true);
    expect($translations)->toHaveKey('Welcome :user');
    expect($translations)->not->toHaveKey('Welcome {user}');
});

it('exports i18n file with --i18n option', function () {
    $i18nPath = 'resources/js/i18n/en.js';

    $this->artisan('trans:scan', ['--i18n' => $i18nPath])
        ->assertExitCode(0);

    $fullPath = base_path($i18nPath);
    expect(file_exists($fullPath))->toBeTrue();

    $content = File::get($fullPath);
    expect($content)->toContain('export default');
    expect($content)->toContain('{name}');

    // Cleanup
    unlink($fullPath);
});

it('extracts keys from PHP files', function () {
    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get(base_path('resources/lang/en.json')), true);
    expect($translations)->toHaveKey('Dashboard Title');
});

it('extracts keys from Vue files', function () {
    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get(base_path('resources/lang/en.json')), true);
    expect($translations)->toHaveKey('Welcome Back');
});
