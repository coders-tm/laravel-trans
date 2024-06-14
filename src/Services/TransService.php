<?php

namespace Nitro\Trans\Services;

use Illuminate\Support\Facades\File;

class TransService
{
    private array $translations = [];

    private string $locale;

    private string $fallbackLocale;

    private string $langPath;

    private string $storagePath;

    /** @var string[]|null */
    private ?array $supportedLocales = null;

    private static array $fileCache = [];

    public function __construct(string $locale = 'en', string $fallbackLocale = 'en', ?string $langPath = null)
    {
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->langPath = $langPath ?? $this->defaultLangPath();
        $this->storagePath = storage_path('lang');
        $this->load($locale);
    }

    /**
     * Set the current locale.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->load($locale);
    }

    /**
     * Get the current locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Get all supported locales by scanning lang/*.json files.
     *
     * @return string[]
     */
    public function getSupportedLocales(): array
    {
        if ($this->supportedLocales === null) {
            $this->supportedLocales = $this->discoverLocales();
        }

        return $this->supportedLocales;
    }

    /**
     * Get JSON file content with static caching based on path and file modification time.
     *
     * @return array<string, string>
     */
    private function getJsonFileContent(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        try {
            $lastModified = File::lastModified($path);
        } catch (\Throwable) {
            $lastModified = 0;
        }

        $cacheKey = $path.'_'.$lastModified;

        if (isset(self::$fileCache[$cacheKey])) {
            return self::$fileCache[$cacheKey];
        }

        try {
            $content = File::get($path);
            self::$fileCache[$cacheKey] = json_decode($content, true) ?? [];
        } catch (\Throwable) {
            self::$fileCache[$cacheKey] = [];
        }

        return self::$fileCache[$cacheKey];
    }

    /**
     * Get and merge base and storage translations for a locale.
     *
     * @return array<string, string>
     */
    private function merge(string $locale, bool $filterKeys = true): array
    {
        $basePath = $this->langPath.DIRECTORY_SEPARATOR.$locale.'.json';
        if (! File::exists($basePath)) {
            $basePath = $this->langPath.DIRECTORY_SEPARATOR.'en.json';
        }

        $baseTranslations = $this->getJsonFileContent($basePath);

        $storagePath = $this->storagePath.DIRECTORY_SEPARATOR.$locale.'.json';
        $storageTranslations = $this->getJsonFileContent($storagePath);

        if ($filterKeys) {
            $storageTranslations = array_intersect_key($storageTranslations, $baseTranslations);
        }

        return array_merge($baseTranslations, $storageTranslations);
    }

    /**
     * Load translations from JSON files (both resources and storage).
     */
    public function load(string $locale): void
    {
        $this->translations = $this->merge($locale, false);
    }

    /**
     * Get JSON translations formatted for the SPA frontend (converting placeholders).
     *
     * @return array<string, string>
     */
    public function forFrontend(?string $locale = null): array
    {
        $locale = $locale ?? $this->locale;
        $merged = $this->merge($locale, true);

        $converted = [];
        foreach ($merged as $key => $value) {
            $newKey = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '{$1}', $key);

            if (is_string($value)) {
                $newValue = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '{$1}', $value);
                $newValue = str_replace('@', "{'@'}", $newValue);
            } else {
                $newValue = $value;
            }

            $converted[$newKey] = $newValue;
        }

        return $converted;
    }

    /**
     * Get a translated string by exact key lookup.
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        $value = app('translator')->get($key, $replace, $locale);

        if (! is_string($value)) {
            return $key;
        }

        return $value;
    }

    /**
     * Check if a translation key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->translations);
    }

    /**
     * Get all translations as a flat array.
     *
     * @return array<string, string>
     */
    public function all(?string $locale = null): array
    {
        $locale = $locale ?? $this->locale;

        return $this->merge($locale, true);
    }

    /**
     * Discover available locales by scanning both resources and storage directories.
     *
     * @return string[]
     */
    private function discoverLocales(): array
    {
        $baseFiles = glob($this->langPath.DIRECTORY_SEPARATOR.'*.json');
        $storageFiles = glob($this->storagePath.DIRECTORY_SEPARATOR.'*.json');

        $files = array_unique(array_merge($baseFiles ?: [], $storageFiles ?: []));

        $locales = array_map(
            fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
            $files
        );

        sort($locales);

        return array_values(array_unique($locales));
    }

    /**
     * Get the default language files path.
     */
    private function defaultLangPath(): string
    {
        if (defined('LARAVEL_START')) {
            return base_path('resources'.DIRECTORY_SEPARATOR.'lang');
        }

        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang';
    }
}
