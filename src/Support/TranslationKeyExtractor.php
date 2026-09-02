<?php

namespace Trans\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class TranslationKeyExtractor
{
    private array $excludePaths = [];

    private array $phpFunctions;

    private array $jsFunctions;

    private array $scanDirs;

    private array $scanJsDirs;

    private array $configExcludePaths;

    public function __construct(
        ?array $phpFunctions = null,
        ?array $jsFunctions = null,
        ?array $scanDirs = null,
        ?array $scanJsDirs = null,
        ?array $configExcludePaths = null
    ) {
        $this->phpFunctions = $phpFunctions ?? config('trans.php_functions', ['__', 'trans']);
        $this->jsFunctions = $jsFunctions ?? config('trans.js_functions', ['t']);
        $this->scanDirs = $scanDirs ?? config('trans.scan_dirs', []);
        $this->scanJsDirs = $scanJsDirs ?? config('trans.scan_js_dirs', []);
        $this->configExcludePaths = $configExcludePaths ?? config('trans.exclude_paths', []);
    }

    /**
     * Scan the codebase for translation keys.
     *
     * @return string[]
     */
    public function scan(?string $module = null, array $extraExcludePaths = []): array
    {
        $this->excludePaths = array_map(
            fn (string $path) => realpath(base_path($path)) ?: base_path($path),
            array_merge($this->configExcludePaths, $extraExcludePaths)
        );

        if ($module) {
            return $this->scanModule($module);
        }

        $keys = [];
        $keys = array_merge($keys, $this->scanPhpFiles());
        $keys = array_merge($keys, $this->scanJsFiles());

        return $this->processKeys($keys);
    }

    /**
     * Scan a specific module for translation keys.
     *
     * @return string[]
     */
    public function scanModule(string $module): array
    {
        $keys = [];
        $modulePath = base_path("modules/{$module}");

        if (! File::exists($modulePath)) {
            return [];
        }

        $keys = array_merge($keys, $this->findPhpTranslationKeys($modulePath));
        $keys = array_merge($keys, $this->findJsTranslationKeys($modulePath));

        return $this->processKeys($keys);
    }

    /**
     * Extract translation keys from a string of content.
     *
     * @return string[]
     */
    public function extractFromContent(string $content, string $type = 'php'): array
    {
        $keys = [];

        if ($type === 'php') {
            foreach ($this->phpFunctions as $fn) {
                $keys = array_merge($keys, $this->extractStringKeys($content, $fn));
            }
        } else {
            foreach ($this->jsFunctions as $fn) {
                $keys = array_merge($keys, $this->extractStringKeys($content, $fn));
            }
        }

        return $this->processKeys($keys);
    }

    /**
     * Process and filter extracted translation keys.
     *
     * @param  string[]  $keys
     * @return string[]
     */
    public function processKeys(array $keys): array
    {
        $keys = array_filter($keys, function ($key) {
            if (empty($key)) {
                return false;
            }

            if (str_contains($key, '${')) {
                return false;
            }

            if (trim($key) === '') {
                return false;
            }

            if (str_contains($key, '.concat(')) {
                return false;
            }

            if (str_starts_with($key, ',key,ref')) {
                return false;
            }

            return true;
        });

        $keys = array_map(function ($key) {
            return preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', ':$1', $key);
        }, $keys);

        $keys = array_unique($keys);
        sort($keys);

        return array_values($keys);
    }

    /**
     * Extract translation string keys from content for the given function name.
     *
     * @return string[]
     */
    public function extractStringKeys(string $content, string $fnName): array
    {
        $boundary = '(?<=^|[^a-zA-Z0-9])';
        $escaped = preg_quote($fnName, '/');

        $singlePattern = "/{$boundary}{$escaped}\s*\\(\s*'(?P<key>(?:\\\\.|[^'\\\\])*)'/s";
        $doublePattern = "/{$boundary}{$escaped}\s*\\(\s*\"(?P<key>(?:\\\\.|[^\"\\\\])*)\"/s";

        $keys = [];

        if (preg_match_all($singlePattern, $content, $matches)) {
            $keys = array_merge($keys, $matches['key']);
        }

        if (preg_match_all($doublePattern, $content, $matches)) {
            $keys = array_merge($keys, $matches['key']);
        }

        return array_map('stripcslashes', $keys);
    }

    /**
     * Scan PHP/Blade files for translation keys.
     *
     * @return string[]
     */
    protected function scanPhpFiles(): array
    {
        $keys = [];
        $dirs = $this->resolveDirs($this->scanDirs);

        if (empty($dirs)) {
            return [];
        }

        $finder = new Finder;
        $finder->files()
            ->in($dirs)
            ->exclude('node_modules')
            ->exclude('vendor')
            ->ignoreVCS(true)
            ->name(['*.php', '*.blade.php']);

        foreach ($finder as $file) {
            if ($this->shouldExclude($file->getRealPath())) {
                continue;
            }

            $content = $file->getContents();

            foreach ($this->phpFunctions as $fn) {
                $keys = array_merge($keys, $this->extractStringKeys($content, $fn));
            }

            if (basename($file->getRealPath()) === 'menus.php') {
                if (preg_match_all('/\'title\'\s*=>\s*\'([^\']+)\'/', $content, $matches)) {
                    $keys = array_merge($keys, $matches[1]);
                }
                if (preg_match_all('/"title"\s*=>\s*"([^"]+)"/', $content, $matches)) {
                    $keys = array_merge($keys, $matches[1]);
                }
            }
        }

        return $keys;
    }

    /**
     * Scan JS/TS/Vue files for translation keys.
     *
     * @return string[]
     */
    protected function scanJsFiles(): array
    {
        $keys = [];
        $dirs = $this->resolveDirs($this->scanJsDirs);

        if (empty($dirs)) {
            return [];
        }

        $finder = new Finder;
        $finder->files()
            ->in($dirs)
            ->exclude('node_modules')
            ->exclude('vendor')
            ->ignoreVCS(true)
            ->name(['*.js', '*.ts', '*.vue']);

        foreach ($finder as $file) {
            if ($this->shouldExclude($file->getRealPath())) {
                continue;
            }

            foreach ($this->jsFunctions as $fn) {
                $keys = array_merge($keys, $this->extractStringKeys($file->getContents(), $fn));
            }
        }

        return $keys;
    }

    /**
     * @return string[]
     */
    protected function findPhpTranslationKeys(string $dirPath): array
    {
        $keys = [];

        $finder = new Finder;
        $finder->files()
            ->in($dirPath)
            ->exclude('node_modules')
            ->exclude('vendor')
            ->exclude('public')
            ->ignoreVCS(true)
            ->name(['*.php', '*.blade.php']);

        foreach ($finder as $file) {
            if ($this->shouldExclude($file->getRealPath())) {
                continue;
            }

            foreach ($this->phpFunctions as $fn) {
                $keys = array_merge($keys, $this->extractStringKeys($file->getContents(), $fn));
            }
        }

        return $keys;
    }

    /**
     * @return string[]
     */
    protected function findJsTranslationKeys(string $dirPath): array
    {
        $keys = [];

        $finder = new Finder;
        $finder->files()
            ->in($dirPath)
            ->exclude('node_modules')
            ->exclude('vendor')
            ->exclude('public')
            ->ignoreVCS(true)
            ->name(['*.js', '*.ts', '*.vue']);

        foreach ($finder as $file) {
            if ($this->shouldExclude($file->getRealPath())) {
                continue;
            }

            foreach ($this->jsFunctions as $fn) {
                $keys = array_merge($keys, $this->extractStringKeys($file->getContents(), $fn));
            }
        }

        return $keys;
    }

    /**
     * Resolve directories to absolute paths, filtering out non-existent ones.
     *
     * @param  string[]  $dirs
     * @return string[]
     */
    protected function resolveDirs(array $dirs): array
    {
        $resolved = [];
        foreach ($dirs as $dir) {
            // Support both absolute paths and paths relative to base_path
            $path = is_dir($dir) ? $dir : base_path($dir);
            if (File::exists($path)) {
                $resolved[] = $path;
            }
        }

        return $resolved;
    }

    /**
     * Determine if the file path should be excluded.
     */
    protected function shouldExclude(string $realPath): bool
    {
        $realPath = str_replace('\\', '/', $realPath);
        foreach ($this->excludePaths as $excludePath) {
            $excludePath = str_replace('\\', '/', $excludePath);
            if (str_starts_with(strtolower($realPath), strtolower($excludePath))) {
                return true;
            }
        }

        return false;
    }
}
