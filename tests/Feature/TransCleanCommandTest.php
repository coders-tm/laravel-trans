<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->langPath = base_path('resources/lang');
    if (! file_exists($this->langPath)) {
        mkdir($this->langPath, 0755, true);
    }
});

afterEach(function () {
    $enJson = $this->langPath.'/en.json';
    if (file_exists($enJson)) {
        unlink($enJson);
    }
    $esJson = $this->langPath.'/es.json';
    if (file_exists($esJson)) {
        unlink($esJson);
    }
});

it('removes unused keys from en.json', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Used Key' => 'Used Value',
        'Unused Key' => 'Unused Value',
    ], JSON_PRETTY_PRINT));

    // Create a file that only uses "Used Key"
    file_put_contents($this->tempPath.'/test.blade.php', '<p>{{ __("Used Key") }}</p>');

    $this->artisan('trans:clean')
        ->expectsConfirmation('Are you sure you want to delete these keys? This will update your language files.', 'yes')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveKey('Used Key');
    expect($translations)->not->toHaveKey('Unused Key');
});

it('dry-run does not modify files', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Unused Key' => 'Unused Value',
    ], JSON_PRETTY_PRINT));

    $originalContent = File::get($this->langPath.'/en.json');

    $this->artisan('trans:clean', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(File::get($this->langPath.'/en.json'))->toBe($originalContent);
});

it('cleans other locale files too', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Used Key' => 'Used',
        'Unused Key' => 'Unused',
    ], JSON_PRETTY_PRINT));

    File::put($this->langPath.'/es.json', json_encode([
        'Used Key' => 'Usado',
        'Unused Key' => 'No usado',
    ], JSON_PRETTY_PRINT));

    file_put_contents($this->tempPath.'/test.blade.php', '<p>{{ __("Used Key") }}</p>');

    $this->artisan('trans:clean')
        ->expectsConfirmation('Are you sure you want to delete these keys? This will update your language files.', 'yes')
        ->assertExitCode(0);

    $esTranslations = json_decode(File::get($this->langPath.'/es.json'), true);
    expect($esTranslations)->toHaveKey('Used Key');
    expect($esTranslations)->not->toHaveKey('Unused Key');
});

it('keeps all keys when everything is used', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Key One' => 'Value 1',
        'Key Two' => 'Value 2',
    ], JSON_PRETTY_PRINT));

    file_put_contents($this->tempPath.'/test.blade.php', '<p>{{ __("Key One") }} {{ __("Key Two") }}</p>');

    $this->artisan('trans:clean')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveCount(2);
});
