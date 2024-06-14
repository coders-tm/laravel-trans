<?php

namespace Nitro\Trans\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TransExportCsvCommand extends Command
{
    protected $signature = 'trans:export-csv
                            {--module= : Export only the specified module\'s lang files}';

    protected $description = 'Export all translations from JSON files to a single CSV file';

    public function handle(): int
    {
        $module = $this->option('module');
        $langPath = $module
            ? base_path("modules/{$module}/resources/lang")
            : base_path('resources/lang');
        $path = $module
            ? base_path("modules/{$module}/resources/lang/translations.csv")
            : base_path('resources/lang/translations.csv');

        if (! File::exists($langPath)) {
            $this->error('Language directory not found.');

            return self::FAILURE;
        }

        $enPath = $langPath.'/en.json';

        if (! File::exists($enPath)) {
            $this->error('Default language file en.json not found. Run trans:scan first.');

            return self::FAILURE;
        }

        $enTranslations = json_decode(File::get($enPath), true) ?? [];
        $masterKeys = array_keys($enTranslations);
        sort($masterKeys);

        $jsonFiles = File::glob($langPath.'/*.json');
        $locales = ['en'];
        $masterTranslations = ['en' => $enTranslations];

        foreach ($jsonFiles as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME);

            if ($locale === 'en') {
                continue;
            }

            $locales[] = $locale;
            $masterTranslations[$locale] = json_decode(File::get($file), true) ?? [];
        }

        $this->info('Found locales: '.implode(', ', $locales));

        if ($module) {
            $this->info("Exporting from module [{$module}] lang files.");
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Could not open file {$path} for writing.");

            return self::FAILURE;
        }

        fputcsv($handle, array_merge(['key'], $locales));

        foreach ($masterKeys as $key) {
            $row = [$key];
            foreach ($locales as $locale) {
                $row[] = $masterTranslations[$locale][$key] ?? '';
            }
            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->info('Exported '.count($masterKeys)." keys to {$path}");

        return self::SUCCESS;
    }
}
