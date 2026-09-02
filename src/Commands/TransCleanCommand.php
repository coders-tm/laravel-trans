<?php

namespace Trans\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Trans\Support\TranslationKeyExtractor;

class TransCleanCommand extends Command
{
    protected $signature = 'trans:clean
                            {--dry-run : Display what keys would be removed without modifying files}
                            {--exclude-locales : Do not clean other locale JSON files, only clean en.json}
                            {--exclude=* : Paths to exclude when scanning}
                            {--module= : Clean only the specified module\'s lang files}';

    protected $description = 'Clean duplicated or unused keys from translation JSON files based on codebase scan';

    public function handle(TranslationKeyExtractor $extractor): int
    {
        $module = $this->option('module');

        if ($module) {
            $this->info("Scanning module [{$module}] for translation keys...");
        } else {
            $this->info('Scanning codebase for translation keys...');
        }

        $extraExcludes = $this->parseExcludeOption();
        $scannedKeys = $extractor->scan($module, $extraExcludes);

        $this->info('Found '.count($scannedKeys).' unique translation keys in the codebase.');

        $langPath = $module
            ? base_path("modules/{$module}/resources/lang")
            : base_path('resources/lang');
        $enPath = $langPath.'/en.json';

        if (! File::exists($enPath)) {
            $this->error('Default language file en.json not found.');

            return self::FAILURE;
        }

        $enTranslations = json_decode(File::get($enPath), true);
        if (! is_array($enTranslations)) {
            $this->error('Invalid JSON structure in en.json.');

            return self::FAILURE;
        }

        $existingKeys = array_keys($enTranslations);
        $this->info('Total keys in en.json: '.count($existingKeys));

        $scannedKeysSet = [];
        foreach ($scannedKeys as $key) {
            $scannedKeysSet[$key] = true;
            $normalized = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', ':$1', $key);
            $scannedKeysSet[$normalized] = true;
        }

        $unusedKeys = [];
        foreach ($existingKeys as $key) {
            if (! isset($scannedKeysSet[$key])) {
                $unusedKeys[] = $key;
            }
        }

        if (empty($unusedKeys)) {
            $this->info('No unused translation keys found! en.json is clean.');

            if (! $this->option('dry-run')) {
                ksort($enTranslations);
                File::put($enPath, json_encode($enTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info('Re-sorted and cleaned format of en.json successfully.');
            }

            return self::SUCCESS;
        }

        $this->warn('Found '.count($unusedKeys).' unused translation keys:');
        foreach ($unusedKeys as $key) {
            $this->line("  - {$key}");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: No files were modified.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Are you sure you want to delete these keys? This will update your language files.', true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $cleanedEnTranslations = array_diff_key($enTranslations, array_flip($unusedKeys));
        ksort($cleanedEnTranslations);
        File::put($enPath, json_encode($cleanedEnTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Successfully cleaned en.json.');

        if (! $this->option('exclude-locales')) {
            $jsonFiles = File::glob($langPath.'/*.json');
            foreach ($jsonFiles as $file) {
                $locale = pathinfo($file, PATHINFO_FILENAME);
                if ($locale === 'en') {
                    continue;
                }

                $translations = json_decode(File::get($file), true);
                if (is_array($translations)) {
                    $cleanedTranslations = array_diff_key($translations, array_flip($unusedKeys));
                    ksort($cleanedTranslations);
                    File::put($file, json_encode($cleanedTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->info("Successfully cleaned {$locale}.json.");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    protected function parseExcludeOption(): array
    {
        $excludeOption = $this->option('exclude') ?: [];
        $paths = [];
        foreach ($excludeOption as $value) {
            if (str_contains($value, ',')) {
                $paths = array_merge($paths, array_map('trim', explode(',', $value)));
            } else {
                $paths[] = trim($value);
            }
        }

        return $paths;
    }
}
