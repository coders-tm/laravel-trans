<?php

namespace Trans\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TransImportCsvCommand extends Command
{
    protected $signature = 'trans:import-csv
                            {--module= : Import into the specified module\'s lang files}';

    protected $description = 'Import translations from a CSV file into JSON files';

    public function handle(): int
    {
        $module = $this->option('module');
        $path = $module
            ? base_path("modules/{$module}/resources/lang/translations.csv")
            : base_path('resources/lang/translations.csv');

        if (! File::exists($path)) {
            $this->error("CSV file not found at {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open file {$path} for reading.");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if ($header === false || ! in_array('key', $header)) {
            $this->error('Invalid CSV format. Header "key" column is missing.');
            fclose($handle);

            return self::FAILURE;
        }

        $locales = array_filter($header, function ($col) {
            return $col !== 'key';
        });

        $this->info('Importing locales: '.implode(', ', $locales));

        if ($module) {
            $this->info("Importing into module [{$module}] lang files.");
        }

        $translations = [];
        foreach ($locales as $locale) {
            $translations[$locale] = [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || $row === [null] || (count($row) === 1 && ($row[0] === null || $row[0] === ''))) {
                continue;
            }

            $headerCount = count($header);
            $rowCount = count($row);

            if ($rowCount !== $headerCount) {
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                } else {
                    $row = array_slice($row, 0, $headerCount);
                }
            }

            $data = array_combine($header, $row);
            $key = $data['key'] ?? null;

            if (empty($key)) {
                continue;
            }

            foreach ($locales as $locale) {
                $val = $data[$locale] ?? '';
                if ($val !== '') {
                    $translations[$locale][$key] = $val;
                }
            }
        }

        fclose($handle);

        $langPath = $module
            ? base_path("modules/{$module}/resources/lang")
            : base_path('resources/lang');

        if (! File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }

        foreach ($translations as $locale => $items) {
            if (empty($items)) {
                continue;
            }

            $localePath = $langPath.'/'.$locale.'.json';
            $existingData = [];

            if (File::exists($localePath)) {
                $existingData = json_decode(File::get($localePath), true) ?? [];
            }

            $finalData = array_merge($existingData, $items);
            ksort($finalData);

            File::put($localePath, json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Updated {$locale}.json with ".count($items).' keys from CSV.');
        }

        $this->info('Success! Translations imported successfully.');

        return self::SUCCESS;
    }
}
