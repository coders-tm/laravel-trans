<?php

use Illuminate\Support\Facades\File;
use Trans\Services\TransService;

$originalEnContent = file_get_contents(dirname(__DIR__, 2).'/workbench/resources/lang/en.json');

afterEach(function () use ($originalEnContent) {
    File::put(base_path('resources/lang/en.json'), $originalEnContent);
    @unlink(storage_path('lang/en.json'));
});

it('initializes with correct locale', function () {
    $service = new TransService('en', 'en');

    expect($service->getLocale())->toBe('en');
});

it('returns locale via getLocale', function () {
    $service = new TransService('es', 'en');

    expect($service->getLocale())->toBe('es');
});

it('changes locale via setLocale', function () {
    $service = new TransService('en', 'en');

    $service->setLocale('es');

    expect($service->getLocale())->toBe('es');
});

it('retrieves a translation via get', function () {
    $service = new TransService('en', 'en');

    $result = $service->get('Accept');

    expect($result)->toBe('Accept');
});

it('returns the key itself when translation not found', function () {
    $service = new TransService('en', 'en');

    $result = $service->get('non.existent.key');

    expect($result)->toBe('non.existent.key');
});

it('checks key existence via has', function () {
    $service = new TransService('en', 'en');

    expect($service->has('Accept'))->toBeTrue();
    expect($service->has('non.existent.key'))->toBeFalse();
});

it('returns all translations via all', function () {
    $service = new TransService('en', 'en');

    $all = $service->all();

    expect($all)->toBeArray();
    expect($all)->toHaveKey('Accept');
    expect($all)->toHaveKey('Hello :name');
});

it('returns i18n format via i18n', function () {
    $service = new TransService('en', 'en');

    $result = $service->i18n();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('Accept');
});

it('converts :placeholder to {placeholder} in i18n', function () {
    $service = new TransService('en', 'en');

    $i18n = $service->i18n();

    expect($i18n)->toHaveKey('Hello {name}');
    expect($i18n)->not->toHaveKey('Hello :name');
    expect($i18n['Hello {name}'])->toBe('Hello {name}');
});

it('escapes @ for vue-i18n in i18n', function () {
    $service = new TransService('en', 'en');

    $result = $service->i18n();

    expect($result)->toHaveKey('{app_name} © {year} All Rights Reserved');
    expect($result['{app_name} © {year} All Rights Reserved'])->toBe('{app_name} © {year} All Rights Reserved');
});

it('discovers supported locales via locales', function () {
    $service = new TransService('en', 'en');

    $locales = $service->locales();

    expect($locales)->toBeArray();
    expect($locales)->toContain('en');
    expect($locales)->toContain('es');
});

it('falls back to en when locale file missing', function () {
    $service = new TransService('fr', 'en');

    $all = $service->all('fr');

    // fr.json doesn't exist, so it should fall back to en.json
    expect($all)->toHaveKey('Accept');
});

it('stores translations via update', function () {
    $service = new TransService('en', 'en');

    $result = $service->update('en', ['New Key' => 'New Value']);

    expect($result)->toHaveKey('New Key');
    expect($result['New Key'])->toBe('New Value');

    // Verify it persisted to storage
    $storagePath = config('trans.storage_path', storage_path('lang')).'/en.json';
    expect(File::exists($storagePath))->toBeTrue();
    $stored = json_decode(File::get($storagePath), true);
    expect($stored)->toHaveKey('New Key');
});

it('merges update with existing translations', function () {
    $service = new TransService('en', 'en');

    // First update
    $service->update('en', ['Key A' => 'Value A']);

    // Second update - should merge, not replace
    $result = $service->update('en', ['Key B' => 'Value B']);

    expect($result)->toHaveKey('Key A');
    expect($result)->toHaveKey('Key B');
    expect($result['Key A'])->toBe('Value A');
    expect($result['Key B'])->toBe('Value B');
});

it('filters null values in update', function () {
    $service = new TransService('en', 'en');

    $result = $service->update('en', [
        'Keep Me' => 'yes',
        'Remove Me' => null,
    ]);

    expect($result)->toHaveKey('Keep Me');
    expect($result)->not->toHaveKey('Remove Me');
});

it('reloads translations after update', function () {
    $service = new TransService('en', 'en');

    expect($service->has('Fresh Key'))->toBeFalse();

    $service->update('en', ['Fresh Key' => 'Fresh Value']);

    expect($service->has('Fresh Key'))->toBeTrue();
});

it('sorts keys alphabetically after update', function () {
    $service = new TransService('en', 'en');

    $result = $service->update('en', [
        'Zebra' => 'z',
        'Alpha' => 'a',
        'Middle' => 'm',
    ]);

    $keys = array_keys($result);
    expect($keys)->toBe([...$keys]);
    // Verify Alpha comes before Zebra
    $alphaIndex = array_search('Alpha', $keys);
    $zebraIndex = array_search('Zebra', $keys);
    expect($alphaIndex)->toBeLessThan($zebraIndex);
});
