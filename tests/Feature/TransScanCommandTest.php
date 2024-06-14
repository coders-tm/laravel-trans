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
});

it('scans codebase and creates en.json with found keys', function () {
    $bladeFile = $this->factory()->blade('test.blade.php', '<p>{{ __("Hello World") }}</p>');
    $jsFile = $this->factory()->js('app.js', "const msg = t('Submit Form');");

    // Verify files exist
    expect(file_exists($bladeFile))->toBeTrue();
    expect(file_exists($jsFile))->toBeTrue();

    // Debug: check config
    dump('scan_dirs config:', config('trans.scan_dirs'));
    dump('base_path:', base_path());

    // Debug: manually extract keys
    $extractor = app(\Nitro\Trans\Support\TranslationKeyExtractor::class);
    $keys = $extractor->scan();
    dump('extracted keys:', $keys);

    // Debug: check what Finder finds
    $finder = new \Symfony\Component\Finder\Finder;
    $finder->files()
        ->in($this->tempPath)
        ->name(['*.php', '*.blade.php', '*.js', '*.ts', '*.vue']);
    $foundFiles = [];
    foreach ($finder as $file) {
        $foundFiles[] = $file->getRealPath();
    }
    dump('finder found files:', $foundFiles);
    dump('temp path exists:', file_exists($this->tempPath));
    dump('temp path contents:', scandir($this->tempPath));

    expect(true)->toBeTrue();

    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $enPath = $this->langPath.'/en.json';
    expect(file_exists($enPath))->toBeTrue();

    $translations = json_decode(File::get($enPath), true);
    expect($translations)->toHaveKeys(['Hello World', 'Submit Form']);
});

it('preserves existing translations in en.json', function () {
    File::put($this->langPath.'/en.json', json_encode([
        'Existing Key' => 'Existing Value',
    ], JSON_PRETTY_PRINT));

    $this->factory()->blade('test.blade.php', '<p>{{ __("New Key") }}</p>');

    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveKeys(['Existing Key', 'New Key']);
    expect($translations['Existing Key'])->toBe('Existing Value');
});

it('dry-run does not modify files', function () {
    $this->factory()->blade('test.blade.php', '<p>{{ __("Dry Run Key") }}</p>');

    $this->artisan('trans:scan', ['--dry-run' => true])
        ->assertExitCode(0);

    $enPath = $this->langPath.'/en.json';
    expect(file_exists($enPath))->toBeFalse();
});

it('normalizes placeholders in scanned keys', function () {
    $this->factory()->blade('test.blade.php', '<p>{{ __("Hello :name") }}</p>');
    $this->factory()->js('app.js', "t('Welcome {user}')");

    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveKeys(['Hello :name', 'Welcome :user']);
});

it('exports i18n file with --i18n option', function () {
    $this->factory()->blade('test.blade.php', '<p>{{ __("Hello :name") }}</p>');

    $i18nPath = 'resources/js/i18n/en.js';
    $this->artisan('trans:scan', ['--i18n' => $i18nPath])
        ->assertExitCode(0);

    $fullPath = base_path($i18nPath);
    expect(file_exists($fullPath))->toBeTrue();

    $content = File::get($fullPath);
    expect($content)->toContain("export default");
    expect($content)->toContain('{name}');

    // Cleanup
    unlink($fullPath);
    $dir = dirname($fullPath);
    if (file_exists($dir) && $dir !== base_path('resources/js')) {
        rmdir($dir);
    }
});

it('extracts keys from PHP files', function () {
    $this->factory()->php('Controller.php', "<?php\necho __('Dashboard Title');");

    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveKey('Dashboard Title');
});

it('extracts keys from Vue files', function () {
    $this->factory()->vue('App.vue', <<<'VUE'
<template>
    <p>{{ t('Welcome Back') }}</p>
</template>
VUE
    );

    $this->artisan('trans:scan')
        ->assertExitCode(0);

    $translations = json_decode(File::get($this->langPath.'/en.json'), true);
    expect($translations)->toHaveKey('Welcome Back');
});
