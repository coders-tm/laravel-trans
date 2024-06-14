<?php

namespace Nitro\Trans\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class TransMakeTranslatableCommand extends Command
{
    protected $signature = 'trans:make-translatable
                            {path? : Specific file or directory to process}
                            {--dry-run : Only show what would be done without modifying files}
                            {--module= : Only process files within the specified module}';

    protected $description = 'Automatically wrap plain text in Blade views, Controllers, Vue templates, and JS/TS files with translation helpers';

    /** @var array<string, string> */
    protected array $translations = [];

    /** @var string[] */
    protected array $processedFiles = [];

    public function handle(): int
    {
        $inputPath = $this->argument('path');
        $dryRun = $this->option('dry-run');
        $module = $this->option('module');

        if ($dryRun) {
            $this->warn('DRY-RUN MODE: No files will be modified.');
        }

        $filesToProcess = $this->resolveFiles($inputPath, $module);

        $ignorePatterns = $this->getIgnorePatterns();
        if (! empty($ignorePatterns)) {
            $filesToProcess = array_filter($filesToProcess, function ($file) use ($ignorePatterns) {
                $relativePath = str_replace(base_path().'/', '', $file);
                foreach ($ignorePatterns as $pattern) {
                    if (preg_match($pattern, $relativePath)) {
                        return false;
                    }
                }

                return true;
            });
        }

        $totalFiles = count($filesToProcess);
        $this->info("Found {$totalFiles} files to check.");

        if ($totalFiles === 0) {
            $this->warn('No files found to process.');

            return self::SUCCESS;
        }

        $successCount = 0;
        foreach ($filesToProcess as $filePath) {
            if ($this->processFileInPlace($filePath, $dryRun)) {
                $successCount++;
            }
        }

        $this->info("\nProcessing Summary:");
        $this->info("   Files modified: {$successCount}/{$totalFiles}");
        $this->info('   Translations found: '.count($this->translations));

        if (count($this->processedFiles) > 0) {
            $this->info("\nModified files:");
            foreach ($this->processedFiles as $file) {
                $relativePath = str_replace(base_path().'/', '', $file);
                $this->line("   - {$relativePath}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    protected function resolveFiles(?string $inputPath, ?string $module): array
    {
        $filesToProcess = [];

        if ($inputPath) {
            // Support both absolute paths and paths relative to base_path
            $fullPath = is_dir($inputPath) || is_file($inputPath) ? $inputPath : base_path($inputPath);
            if (! File::exists($fullPath)) {
                $this->error("Path does not exist: {$inputPath}");

                return [];
            }

            if (File::isDirectory($fullPath)) {
                $filesToProcess = array_merge(
                    $this->getBladeFiles($fullPath),
                    $this->getControllerFiles($fullPath),
                    $this->getVueFiles($fullPath),
                    $this->getJsFiles($fullPath)
                );
            } else {
                $filesToProcess = [$fullPath];
            }

            return $filesToProcess;
        }

        if ($module) {
            $this->info("Scanning module [{$module}]...");
            $modulePath = base_path("modules/{$module}");

            $viewPaths = [$modulePath.'/resources/views'];
            $controllerPaths = [$modulePath.'/app/Http/Controllers'];
            $jsPaths = [$modulePath.'/resources/js'];
        } else {
            $this->info('Scanning all Blade views, Controllers, Vue components, and JS/TS files...');
            $viewPaths = array_merge([base_path('resources/views')], $this->getModulePaths('resources/views'));
            $controllerPaths = array_merge([base_path('app/Http/Controllers')], $this->getModulePaths('app/Http/Controllers'));
            $jsPaths = array_merge([base_path('resources/js')], $this->getModulePaths('resources/js'));
        }

        foreach ($viewPaths as $dir) {
            if (File::isDirectory($dir)) {
                $filesToProcess = array_merge($filesToProcess, $this->getBladeFiles($dir));
            }
        }

        foreach ($controllerPaths as $dir) {
            if (File::isDirectory($dir)) {
                $filesToProcess = array_merge($filesToProcess, $this->getControllerFiles($dir));
            }
        }

        foreach ($jsPaths as $dir) {
            if (File::isDirectory($dir)) {
                $filesToProcess = array_merge($filesToProcess, $this->getVueFiles($dir), $this->getJsFiles($dir));
            }
        }

        return $filesToProcess;
    }

    /**
     * @return string[]
     */
    protected function getModulePaths(string $subdir): array
    {
        $paths = [];
        $modulesPath = base_path('modules');
        if (File::isDirectory($modulesPath)) {
            foreach (File::directories($modulesPath) as $moduleDir) {
                $path = $moduleDir.'/'.$subdir;
                if (File::isDirectory($path)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    protected function getBladeFiles(string $dirPath): array
    {
        $files = [];
        $finder = new Finder;
        $finder->files()
            ->in($dirPath)
            ->name('*.blade.php')
            ->exclude(['node_modules', '.git', 'vendor', 'storage', 'bootstrap']);

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @return string[]
     */
    protected function getControllerFiles(string $dirPath): array
    {
        $files = [];
        $finder = new Finder;
        $finder->files()
            ->in($dirPath)
            ->name('*.php')
            ->notName('*.blade.php')
            ->exclude(['node_modules', '.git', 'vendor', 'storage', 'bootstrap']);

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @return string[]
     */
    protected function getVueFiles(string $dirPath): array
    {
        $files = [];
        $finder = new Finder;
        $finder->files()
            ->in($dirPath)
            ->name('*.vue')
            ->exclude(['node_modules', '.git', 'vendor', 'storage', 'bootstrap']);

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @return string[]
     */
    protected function getJsFiles(string $dirPath): array
    {
        $files = [];
        $finder = new Finder;
        $finder->files()
            ->in($dirPath)
            ->name(['*.js', '*.ts'])
            ->exclude(['node_modules', '.git', 'vendor', 'storage', 'bootstrap']);

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    protected function processFileInPlace(string $filePath, bool $dryRun): bool
    {
        try {
            $content = File::get($filePath);

            if (str_ends_with($filePath, '.blade.php')) {
                $processed = $this->processBladeContent($content);
            } elseif (str_ends_with($filePath, '.vue')) {
                $processed = $this->processVueContent($content);
            } elseif (str_ends_with($filePath, '.php')) {
                $processed = $this->processPhpContent($content);
            } elseif (str_ends_with($filePath, '.js') || str_ends_with($filePath, '.ts')) {
                $processed = $this->processJsContent($content);
            } else {
                return false;
            }

            if ($processed !== $content) {
                if (! $dryRun) {
                    File::put($filePath, $processed);
                }
                $this->processedFiles[] = $filePath;
                $relativePath = str_replace(base_path().'/', '', $filePath);
                $this->line("Updated file: {$relativePath}");

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->error("Error processing file {$filePath}: ".$e->getMessage());

            return false;
        }
    }

    protected function processPhpContent(string $content): string
    {
        $protected = [];
        $content = $this->protectPhpValidationAndRules($content, $protected);

        $phpKeys = config('trans.make_translatable.php_keys', ['error', 'success', 'message', 'warning', 'title']);
        $keysPattern = implode('|', $phpKeys);

        // Match array keys like 'error' => 'Unsubscribe link is invalid or expired.'
        $arrayPattern = '/([\'"])('.$keysPattern.')\1\s*=>\s*([\'"])(.*?)(?<!\\\\)\3/s';
        $content = preg_replace_callback($arrayPattern, function ($matches) {
            $keyQuote = $matches[1];
            $key = $matches[2];
            $valQuote = $matches[3];
            $val = $matches[4];

            if (preg_match('/^__\(/', $val)) {
                return $matches[0];
            }

            if ($this->shouldTranslate($val)) {
                $this->translations[$val] = $val;

                return "{$keyQuote}{$key}{$keyQuote} => __({$valQuote}{$val}{$valQuote})";
            }

            return $matches[0];
        }, $content);

        // Match with() calls like with('error|success', 'Foo bar')
        $withPattern = '/(with\s*\(\s*([\'"])('.$keysPattern.')\2\s*,\s*)([\'"])(.*?)(?<!\\\\)\4\s*\)/s';
        $content = preg_replace_callback($withPattern, function ($matches) {
            $prefix = $matches[1];
            $valQuote = $matches[4];
            $val = $matches[5];

            if (preg_match('/^__\(/', $val)) {
                return $matches[0];
            }

            if ($this->shouldTranslate($val)) {
                $this->translations[$val] = $val;

                return "{$prefix}__({$valQuote}{$val}{$valQuote}))";
            }

            return $matches[0];
        }, $content);

        // Match magic withError('Foo bar') / withSuccess('Foo bar') etc.
        $magicWithPattern = '/(with(Error|Success|Message|Warning)\s*\(\s*)([\'"])(.*?)(?<!\\\\)\3\s*\)/s';
        $content = preg_replace_callback($magicWithPattern, function ($matches) {
            $prefix = $matches[1];
            $valQuote = $matches[3];
            $val = $matches[4];

            if (preg_match('/^__\(/', $val)) {
                return $matches[0];
            }

            if ($this->shouldTranslate($val)) {
                $this->translations[$val] = $val;

                return "{$prefix}__({$valQuote}{$val}{$valQuote}))";
            }

            return $matches[0];
        }, $content);

        // Restore placeholders in reverse order
        $placeholders = array_keys($protected);
        for ($i = count($placeholders) - 1; $i >= 0; $i--) {
            $content = str_replace($placeholders[$i], $protected[$placeholders[$i]], $content);
        }

        return $content;
    }

    protected function protectPhpValidationAndRules(string $content, array &$protected): string
    {
        $len = strlen($content);

        // Protect $rules array assignments
        $rulesPattern = '/(?<![a-zA-Z0-9_])\$rules\s*=\s*\[/i';
        if (preg_match_all($rulesPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            for ($j = count($matches[0]) - 1; $j >= 0; $j--) {
                $startPos = $matches[0][$j][1];

                if ($this->isInsideStringOrComment($content, $startPos)) {
                    continue;
                }

                $bracketStart = strpos($content, '[', $startPos);
                if ($bracketStart !== false) {
                    $closePos = $this->findMatchingChar($content, $bracketStart + 1, '[', ']');
                    if ($closePos !== null) {
                        $i = $closePos + 1;
                        while ($i < $len && in_array($content[$i], [' ', "\t", "\r", "\n"])) {
                            $i++;
                        }
                        $endPos = ($i < $len && $content[$i] === ';') ? $i + 1 : $closePos + 1;

                        $fullMatch = substr($content, $startPos, $endPos - $startPos);
                        $placeholder = '__PHP_RULES_PLACEHOLDER_'.count($protected).'__';
                        $protected[$placeholder] = $fullMatch;
                        $content = substr_replace($content, $placeholder, $startPos, $endPos - $startPos);
                        $len = strlen($content);
                    }
                }
            }
        }

        // Protect validation blocks
        $validationPattern = '/(?<![a-zA-Z0-9_])((?:\$request->)?validate|Validator::make)\s*\(/i';
        if (preg_match_all($validationPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            for ($j = count($matches[0]) - 1; $j >= 0; $j--) {
                $startPos = $matches[0][$j][1];

                if ($this->isInsideStringOrComment($content, $startPos)) {
                    continue;
                }

                $parenthesisStart = strpos($content, '(', $startPos);
                if ($parenthesisStart !== false) {
                    $closePos = $this->findMatchingChar($content, $parenthesisStart + 1, '(', ')');
                    if ($closePos !== null) {
                        $endPos = $closePos + 1;
                        $fullMatch = substr($content, $startPos, $endPos - $startPos);
                        $placeholder = '__PHP_VALIDATION_PLACEHOLDER_'.count($protected).'__';
                        $protected[$placeholder] = $fullMatch;
                        $content = substr_replace($content, $placeholder, $startPos, $endPos - $startPos);
                        $len = strlen($content);
                    }
                }
            }
        }

        return $content;
    }

    protected function findMatchingChar(string $content, int $startPos, string $openChar, string $closeChar): ?int
    {
        $depth = 1;
        $len = strlen($content);
        $i = $startPos;

        while ($i < $len && $depth > 0) {
            $char = $content[$i];

            if ($char === '/' && $i + 1 < $len) {
                if ($content[$i + 1] === '/') {
                    $i += 2;
                    while ($i < $len && $content[$i] !== "\n" && $content[$i] !== "\r") {
                        $i++;
                    }
                    continue;
                } elseif ($content[$i + 1] === '*') {
                    $i += 2;
                    while ($i + 1 < $len && ! ($content[$i] === '*' && $content[$i + 1] === '/')) {
                        $i++;
                    }
                    $i += 2;
                    continue;
                }
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $i++;
                while ($i < $len) {
                    if ($content[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($content[$i] === $quote) {
                        break;
                    }
                    $i++;
                }
            }

            if ($content[$i] === $openChar) {
                $depth++;
            } elseif ($content[$i] === $closeChar) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }

            $i++;
        }

        return null;
    }

    protected function isInsideStringOrComment(string $content, int $pos): bool
    {
        $len = strlen($content);
        $i = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inSingleLineComment = false;
        $inMultiLineComment = false;

        while ($i < $pos && $i < $len) {
            $char = $content[$i];

            if ($inSingleLineComment) {
                if ($char === "\n" || $char === "\r") {
                    $inSingleLineComment = false;
                }
                $i++;
                continue;
            }

            if ($inMultiLineComment) {
                if ($char === '*' && $i + 1 < $len && $content[$i + 1] === '/') {
                    $inMultiLineComment = false;
                    $i += 2;
                } else {
                    $i++;
                }
                continue;
            }

            if ($inSingleQuote) {
                if ($char === '\\') {
                    $i += 2;
                } else {
                    if ($char === "'") {
                        $inSingleQuote = false;
                    }
                    $i++;
                }
                continue;
            }

            if ($inDoubleQuote) {
                if ($char === '\\') {
                    $i += 2;
                } else {
                    if ($char === '"') {
                        $inDoubleQuote = false;
                    }
                    $i++;
                }
                continue;
            }

            if ($char === '/' && $i + 1 < $len) {
                if ($content[$i + 1] === '/') {
                    $inSingleLineComment = true;
                    $i += 2;
                    continue;
                } elseif ($content[$i + 1] === '*') {
                    $inMultiLineComment = true;
                    $i += 2;
                    continue;
                }
            }

            if ($char === "'") {
                $inSingleQuote = true;
            } elseif ($char === '"') {
                $inDoubleQuote = true;
            }

            $i++;
        }

        return $inSingleQuote || $inDoubleQuote || $inSingleLineComment || $inMultiLineComment;
    }

    protected function protectSchemaDirectives(string $content, array &$protected): string
    {
        $offset = 0;
        $placeholderCounter = count($protected);

        while (($pos = strpos($content, '@schema', $offset)) !== false) {
            $startParenthesis = strpos($content, '(', $pos);
            if ($startParenthesis === false || $startParenthesis > $pos + 10) {
                $offset = $pos + 7;
                continue;
            }

            $depth = 1;
            $len = strlen($content);
            $i = $startParenthesis + 1;

            while ($i < $len && $depth > 0) {
                $char = $content[$i];
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                    $i++;
                    while ($i < $len) {
                        if ($content[$i] === '\\') {
                            $i += 2;
                            continue;
                        }
                        if ($content[$i] === $quote) {
                            break;
                        }
                        $i++;
                    }
                }
                $i++;
            }

            if ($depth === 0) {
                $fullSchema = substr($content, $pos, $i - $pos);
                $fullSchema = $this->processSchemaContent($fullSchema);
                $placeholder = "__SCHEMA_PLACEHOLDER_{$placeholderCounter}__";
                $placeholderCounter++;
                $protected[$placeholder] = $fullSchema;
                $content = substr_replace($content, $placeholder, $pos, $i - $pos);
                $offset = $pos + strlen($placeholder);
            } else {
                $offset = $pos + 7;
            }
        }

        return $content;
    }

    protected function processSchemaContent(string $schemaContent): string
    {
        $schemaKeys = config('trans.make_translatable.schema_keys', ['label', 'name', 'placeholder', 'info', 'help']);
        $keysPattern = implode('|', $schemaKeys);

        $pattern = '/([\'"])('.$keysPattern.')\1\s*=>\s*([\'"])(.*?)(?<!\\\\)\3/s';

        return preg_replace_callback($pattern, function ($matches) {
            $keyQuote = $matches[1];
            $key = $matches[2];
            $valQuote = $matches[3];
            $val = $matches[4];

            if (preg_match('/^__\(/', $val)) {
                return $matches[0];
            }

            if ($this->shouldTranslate($val)) {
                $this->translations[$val] = $val;

                return "{$keyQuote}{$key}{$keyQuote} => __({$valQuote}{$val}{$valQuote})";
            }

            return $matches[0];
        }, $schemaContent);
    }

    protected function protectBladeExpressions(string $content, array &$protected): string
    {
        $content = preg_replace_callback('/@php(?!\s*\()([\s\S]*?)@endphp/i', function ($matches) use (&$protected) {
            $placeholder = '__PHP_BLOCK_PLACEHOLDER_'.count($protected).'__';
            $protected[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        $content = preg_replace_callback('/\{\{\-\-([\s\S]*?)\-\-\}\}/', function ($matches) use (&$protected) {
            $placeholder = '__BLADE_COMMENT_PLACEHOLDER_'.count($protected).'__';
            $protected[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        $content = preg_replace_callback('/\{\{\{([\s\S]*?)\}\}\}/', function ($matches) use (&$protected) {
            $placeholder = '__BLADE_ESCAPED_PLACEHOLDER_'.count($protected).'__';
            $protected[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        $content = preg_replace_callback('/\{\{([\s\S]*?)\}\}/', function ($matches) use (&$protected) {
            $placeholder = '__BLADE_ECHO_PLACEHOLDER_'.count($protected).'__';
            $protected[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        $content = preg_replace_callback('/\{!!([\s\S]*?)!!\}/', function ($matches) use (&$protected) {
            $placeholder = '__BLADE_RAW_PLACEHOLDER_'.count($protected).'__';
            $protected[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        return $content;
    }

    protected function protectBladeDirectives(string $content, array &$protected): string
    {
        $offset = 0;
        $placeholderCounter = count($protected);
        $len = strlen($content);

        while (($pos = strpos($content, '@', $offset)) !== false) {
            if ($pos + 1 >= $len || ! preg_match('/^[a-zA-Z_]/', $content[$pos + 1])) {
                $offset = $pos + 1;
                continue;
            }

            preg_match('/^@[a-zA-Z0-9_]+/', substr($content, $pos), $nameMatches);
            if (empty($nameMatches)) {
                $offset = $pos + 1;
                continue;
            }

            $directiveName = $nameMatches[0];
            $nameLen = strlen($directiveName);

            if ($pos > 0 && preg_match('/[a-zA-Z0-9_\.\-]/', $content[$pos - 1])) {
                $offset = $pos + $nameLen;
                continue;
            }

            $searchStart = $pos + $nameLen;
            $hasParenthesis = false;
            $startParenthesis = -1;

            for ($j = $searchStart; $j < $len; $j++) {
                $char = $content[$j];
                if ($char === '(') {
                    $hasParenthesis = true;
                    $startParenthesis = $j;
                    break;
                }
                if ($char !== ' ' && $char !== "\t" && $char !== "\r" && $char !== "\n") {
                    break;
                }
            }

            if ($hasParenthesis && $startParenthesis !== -1) {
                $depth = 1;
                $i = $startParenthesis + 1;

                while ($i < $len && $depth > 0) {
                    $char = $content[$i];
                    if ($char === '(') {
                        $depth++;
                    } elseif ($char === ')') {
                        $depth--;
                    } elseif ($char === '"' || $char === "'") {
                        $quote = $char;
                        $i++;
                        while ($i < $len) {
                            if ($content[$i] === '\\') {
                                $i += 2;
                                continue;
                            }
                            if ($content[$i] === $quote) {
                                break;
                            }
                            $i++;
                        }
                    }
                    $i++;
                }

                if ($depth === 0) {
                    $fullDirective = substr($content, $pos, $i - $pos);
                    $placeholder = "__BLADE_DIRECTIVE_PLACEHOLDER_{$placeholderCounter}__";
                    $placeholderCounter++;
                    $protected[$placeholder] = $fullDirective;
                    $content = substr_replace($content, $placeholder, $pos, $i - $pos);
                    $len = strlen($content);
                    $offset = $pos + strlen($placeholder);
                } else {
                    $offset = $pos + $nameLen;
                }
            } else {
                $placeholder = "__BLADE_DIRECTIVE_PLACEHOLDER_{$placeholderCounter}__";
                $placeholderCounter++;
                $protected[$placeholder] = $directiveName;
                $content = substr_replace($content, $placeholder, $pos, $nameLen);
                $len = strlen($content);
                $offset = $pos + strlen($placeholder);
            }
        }

        return $content;
    }

    protected function protectShortcodes(string $content, array &$protected): string
    {
        return preg_replace_callback('/\[\/?[a-z0-9\-]+(?:\s+[^\]]*)?\](?!\s*\()/i', function ($matches) use (&$protected) {
            $placeholder = '__SHORTCODE_PLACEHOLDER_'.count($protected).'__';
            $protected[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);
    }

    protected function processBladeContent(string $content): string
    {
        $protected = [];
        $content = $this->protectSchemaDirectives($content, $protected);
        $content = $this->protectShortcodes($content, $protected);
        $content = $this->protectBladeExpressions($content, $protected);
        $content = $this->protectBladeDirectives($content, $protected);

        $patterns = [
            '<script[^>]*>[\s\S]*?<\/script>',
            '<style[^>]*>[\s\S]*?<\/style>',
            '><!--[\s\S]*?-->',
            '__(?:PHP_BLOCK|BLADE_COMMENT|BLADE_ESCAPED|BLADE_ECHO|BLADE_RAW|SCHEMA|BLADE_DIRECTIVE|SHORTCODE)_PLACEHOLDER_\d+__',
            '@[a-zA-Z_][\w_]*(?:\((?:[^()]+|\([^()]*\))*\))?',
            '<(?:"[^"]*"|\'[^\']*\'|[^\'">])+>',
        ];

        $regex = '/('.implode('|', $patterns).')/i';
        $tokens = preg_split($regex, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false) {
            return $content;
        }

        $tagStack = [];
        $allowedTags = [
            'span', 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'label', 'strong', 'small', 'dt', 'td', 'th', 'button', 'a', 'li',
        ];
        $selfClosingTags = [
            'img', 'br', 'input', 'meta', 'link', 'hr', 'col',
            'embed', 'param', 'source', 'track', 'wbr',
        ];

        foreach ($tokens as $index => $token) {
            if ($index % 2 === 0) {
                if ($token === '') {
                    continue;
                }

                $currentParent = end($tagStack);
                $shouldTranslateContent = true;
                if ($currentParent !== false) {
                    $parentName = strtolower($currentParent['name']);
                    if (! in_array($parentName, $allowedTags) || $currentParent['skip_content']) {
                        $shouldTranslateContent = false;
                    }
                }

                if ($shouldTranslateContent) {
                    if (preg_match('/^(\s*)([\s\S]*?)(\s*)$/', $token, $matches)) {
                        $leading = $matches[1];
                        $core = $matches[2];
                        $trailing = $matches[3];
                        if ($this->shouldTranslate($core)) {
                            $tokens[$index] = $leading.$this->wrapWithTranslation($core).$trailing;
                        }
                    }
                }
            } else {
                if (str_starts_with($token, '<') && ! str_starts_with($token, '<!--') && ! str_starts_with($token, '<script') && ! str_starts_with($token, '<style')) {
                    if (preg_match('/^<\/\s*([a-zA-Z0-9_\-]+)/', $token, $matches)) {
                        $tagName = strtolower($matches[1]);
                        if (! empty($tagStack)) {
                            array_pop($tagStack);
                        }
                    } elseif (preg_match('/^<\s*([a-zA-Z0-9_\-]+)/', $token, $matches)) {
                        $tagName = strtolower($matches[1]);
                        $isSelfClosing = str_ends_with($token, '/>') || in_array($tagName, $selfClosingTags);

                        if (! $isSelfClosing) {
                            $isMaterialIcon = (bool) preg_match('/class=["\'][^"\']*material-(?:symbols|icons)[^"\']*["\']/i', $token);
                            $tagStack[] = [
                                'name' => $tagName,
                                'skip_content' => $isMaterialIcon,
                            ];
                        }

                        $token = $this->translateTagAttributes($token);
                        $tokens[$index] = $token;
                    }
                }
            }
        }

        $processed = implode('', $tokens);

        $placeholders = array_keys($protected);
        for ($i = count($placeholders) - 1; $i >= 0; $i--) {
            $processed = str_replace($placeholders[$i], $protected[$placeholders[$i]], $processed);
        }

        return $processed;
    }

    protected function translateTagAttributes(string $tagToken): string
    {
        $attributes = config('trans.translatable_attributes.blade', ['placeholder', 'alt', 'title']);

        foreach ($attributes as $attr) {
            $pattern = '/('.$attr.'=["\'])([^"\'{]+)(["\'])/i';
            $tagToken = preg_replace_callback($pattern, function ($matches) {
                $wrappedText = $this->wrapWithTranslation($matches[2]);
                if ($wrappedText !== $matches[2]) {
                    return $matches[1].$wrappedText.$matches[3];
                }

                return $matches[0];
            }, $tagToken);
        }

        return $tagToken;
    }

    protected function shouldTranslate(string $text): bool
    {
        $trimmed = trim($text);

        if (! $trimmed || mb_strlen($trimmed) < 2 || preg_match('/^\d+$/', $trimmed)) {
            return false;
        }

        if (preg_match('/__(?:SCRIPT|STYLE|SCHEMA|PHP_BLOCK|BLADE_COMMENT|BLADE_ESCAPED|BLADE_ECHO|BLADE_RAW|BLADE_DIRECTIVE|SHORTCODE)_PLACEHOLDER_\d+__/', $trimmed) || preg_match('/__(?:[A-Z0-9_]+)__/', $trimmed)) {
            return false;
        }

        if (preg_match('/^[$€£¥₹¢₩₽₺₪₫₴₦₲₵₡₢₣₤₥₧₨₰₱₲₳₴₵₸₺₼₽₾₿]+$/u', $trimmed)) {
            return false;
        }

        if (preg_match('/^(href|src|class|id)\b/i', $trimmed) || preg_match('/^(data-|\/|#|@|::)/i', $trimmed)) {
            return false;
        }

        if (preg_match('/^\[[\w\-]+[\s\w\-="\'\.]*\]$/', $trimmed)) {
            return false;
        }

        if (preg_match('/^\[[\w\-]+[\s\w\-="\'\.]*\].*\[\/[\w\-]+\]$/s', $trimmed)) {
            return false;
        }

        return (bool) preg_match('/[a-zA-Z]/', $trimmed);
    }

    protected function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * @return string[]
     */
    protected function splitAndLimitSentences(string $text): array
    {
        $cleanedText = $this->cleanText($text);

        $sentences = preg_split('/(?<=[.!?])\s+/', $cleanedText);
        $sentences = array_filter(array_map('trim', $sentences ?: []));

        if (count($sentences) <= 2) {
            return [$cleanedText];
        }

        $chunks = [];
        for ($i = 0; $i < count($sentences); $i += 2) {
            $chunks[] = implode(' ', array_slice($sentences, $i, 2));
        }

        return $chunks;
    }

    protected function wrapWithTranslation(string $text): string
    {
        if (! $this->shouldTranslate($text)) {
            return $text;
        }

        if (str_contains($text, '{{') || str_contains($text, '}}')) {
            return $text;
        }

        $textChunks = $this->splitAndLimitSentences($text);

        if (count($textChunks) > 1) {
            $wrappedChunks = [];
            foreach ($textChunks as $chunk) {
                $this->translations[$chunk] = $chunk;
                $safeChunk = json_encode($chunk, JSON_UNESCAPED_UNICODE);
                $wrappedChunks[] = "{{ __({$safeChunk}) }}";
            }

            return implode(' ', $wrappedChunks);
        }

        $cleanText = $textChunks[0];
        $this->translations[$cleanText] = $cleanText;
        $safeText = json_encode($cleanText, JSON_UNESCAPED_UNICODE);

        return "{{ __({$safeText}) }}";
    }

    protected function processVueContent(string $content): string
    {
        $translationsBefore = count($this->translations);

        // Process script blocks first
        $scriptBlocks = [];
        $hasSetup = false;

        $content = preg_replace_callback('/<script(?P<attrs>[^>]*)>(?P<content>[\s\S]*?)<\/script>/i', function ($matches) use (&$scriptBlocks, &$hasSetup) {
            $attrs = $matches['attrs'];
            $scriptContent = $matches['content'];
            $isSetup = str_contains($attrs, 'setup');
            if ($isSetup) {
                $hasSetup = true;
            }

            $processedScript = $this->processJsContent($scriptContent);

            $placeholder = '__VUE_SCRIPT_BLOCK_'.count($scriptBlocks).'__';
            $scriptBlocks[$placeholder] = [
                'attrs' => $attrs,
                'content' => $processedScript,
                'is_setup' => $isSetup,
            ];

            return $placeholder;
        }, $content);

        // Protect style blocks
        $styleBlocks = [];
        $content = preg_replace_callback('/<style[^>]*>[\s\S]*?<\/style>/i', function ($matches) use (&$styleBlocks) {
            $placeholder = '__VUE_STYLE_BLOCK_'.count($styleBlocks).'__';
            $styleBlocks[$placeholder] = $matches[0];

            return $placeholder;
        }, $content);

        // Process the remaining template content
        $patterns = [
            '<!--[\s\S]*?-->',
            '\{\{[\s\S]*?\}\}',
            '<(?:"[^"]*"|\'[^\']*\'|[^\'">])+>',
            '__VUE_SCRIPT_BLOCK_\d+__',
            '__VUE_STYLE_BLOCK_\d+__',
        ];

        $regex = '/('.implode('|', $patterns).')/i';
        $tokens = preg_split($regex, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens !== false) {
            $tagStack = [];
            $disallowedTags = ['script', 'style', 'pre', 'code', 'svg', 'path', 'iframe', 'canvas'];
            $selfClosingTags = ['img', 'br', 'input', 'meta', 'link', 'hr', 'col', 'embed', 'param', 'source', 'track', 'wbr'];

            foreach ($tokens as $index => $token) {
                if ($index % 2 === 0) {
                    if ($token === '') {
                        continue;
                    }

                    $currentParent = end($tagStack);
                    $shouldTranslateContent = true;
                    if ($currentParent !== false) {
                        $parentName = strtolower($currentParent['name']);
                        if (in_array($parentName, $disallowedTags) || $currentParent['skip_content']) {
                            $shouldTranslateContent = false;
                        }
                    }

                    if ($shouldTranslateContent) {
                        if (preg_match('/^(\s*)([\s\S]*?)(\s*)$/', $token, $matches)) {
                            $leading = $matches[1];
                            $core = $matches[2];
                            $trailing = $matches[3];
                            if ($this->shouldTranslate($core)) {
                                $tokens[$index] = $leading.$this->wrapVueWithTranslation($core).$trailing;
                            }
                        }
                    }
                } else {
                    if (str_starts_with($token, '<') && ! str_starts_with($token, '<!--')) {
                        if (preg_match('/^<\/\s*([a-zA-Z0-9_\-]+)/', $token, $matches)) {
                            if (! empty($tagStack)) {
                                array_pop($tagStack);
                            }
                        } elseif (preg_match('/^<\s*([a-zA-Z0-9_\-\:]+)/', $token, $matches)) {
                            $tagName = strtolower($matches[1]);
                            $isSelfClosing = str_ends_with($token, '/>') || in_array($tagName, $selfClosingTags);

                            if (! $isSelfClosing) {
                                $isSkip = in_array($tagName, $disallowedTags);
                                $tagStack[] = [
                                    'name' => $tagName,
                                    'skip_content' => $isSkip,
                                ];
                            }

                            $token = $this->translateVueTagAttributes($token);
                            $tokens[$index] = $token;
                        }
                    }
                }
            }
            $content = implode('', $tokens);
        }

        // Check if translations were added
        $translationsAdded = (count($this->translations) > $translationsBefore);

        // Restore script blocks (injecting useLang if translations were added)
        foreach ($scriptBlocks as $placeholder => $block) {
            $scriptContent = $block['content'];
            if ($translationsAdded) {
                $scriptContent = $this->injectUseLang($scriptContent, $block['is_setup']);
            }
            $originalBlock = "<script{$block['attrs']}>".$scriptContent.'</script>';
            $content = str_replace($placeholder, $originalBlock, $content);
        }

        // Restore style blocks
        foreach ($styleBlocks as $placeholder => $blockHtml) {
            $content = str_replace($placeholder, $blockHtml, $content);
        }

        return $content;
    }

    protected function processJsContent(string $content): string
    {
        $jsKeys = config('trans.make_translatable.js_keys', ['label', 'title', 'placeholder', 'description', 'message', 'subject']);
        $keysPattern = implode('|', $jsKeys);

        $content = str_replace("\r\n", "\n", $content);

        // Single quotes
        $singleRegex = '/\b('.$keysPattern.')\s*:\s*\'((?:\\\\.|[^\'\\\\])*)\'/s';
        $content = preg_replace_callback($singleRegex, function ($matches) {
            $key = $matches[1];
            $text = stripslashes($matches[2]);

            if ($this->shouldTranslate($text)) {
                if (preg_match('/^t\(/', $text)) {
                    return $matches[0];
                }

                $this->translations[$text] = $text;
                $safeText = json_encode($text, JSON_UNESCAPED_UNICODE);

                return "{$key}: t({$safeText})";
            }

            return $matches[0];
        }, $content);

        // Double quotes
        $doubleRegex = '/\b('.$keysPattern.')\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s';
        $content = preg_replace_callback($doubleRegex, function ($matches) {
            $key = $matches[1];
            $text = stripslashes($matches[2]);

            if ($this->shouldTranslate($text)) {
                if (preg_match('/^t\(/', $text)) {
                    return $matches[0];
                }

                $this->translations[$text] = $text;
                $safeText = json_encode($text, JSON_UNESCAPED_UNICODE);

                return "{$key}: t({$safeText})";
            }

            return $matches[0];
        }, $content);

        return $content;
    }

    protected function translateVueTagAttributes(string $tagToken): string
    {
        $attributes = config('trans.translatable_attributes.vue', [
            'placeholder', 'alt', 'title', 'label', 'message', 'hint', 'action-label',
        ]);

        foreach ($attributes as $attr) {
            $pattern = '/\b'.preg_quote($attr, '/').'\s*=\s*(["\'])(.*?)\1/i';

            $tagToken = preg_replace_callback($pattern, function ($matches) use ($attr, $tagToken) {
                $matchedString = $matches[0];
                $pos = strpos($tagToken, $matchedString);
                if ($pos !== false && $pos > 0) {
                    $prefixChar = $tagToken[$pos - 1];
                    if ($prefixChar === ':' || $prefixChar === '-') {
                        return $matches[0];
                    }
                    if ($pos >= 7 && substr($tagToken, $pos - 7, 7) === 'v-bind:') {
                        return $matches[0];
                    }
                }

                $text = $matches[2];
                if ($this->shouldTranslate($text)) {
                    $this->translations[$text] = $text;
                    $escapedText = str_replace("'", "\\'", $text);

                    return ':'.$attr.'="t(\''.$escapedText.'\')"';
                }

                return $matches[0];
            }, $tagToken);
        }

        return $tagToken;
    }

    protected function wrapVueWithTranslation(string $text): string
    {
        if (! $this->shouldTranslate($text)) {
            return $text;
        }

        if (str_contains($text, '{{') || str_contains($text, '}}') || str_contains($text, 't(')) {
            return $text;
        }

        $textChunks = $this->splitAndLimitSentences($text);

        if (count($textChunks) > 1) {
            $wrappedChunks = [];
            foreach ($textChunks as $chunk) {
                $this->translations[$chunk] = $chunk;
                $safeChunk = json_encode($chunk, JSON_UNESCAPED_UNICODE);
                $wrappedChunks[] = "{{ t({$safeChunk}) }}";
            }

            return implode(' ', $wrappedChunks);
        }

        $cleanText = $textChunks[0];
        $this->translations[$cleanText] = $cleanText;
        $safeText = json_encode($cleanText, JSON_UNESCAPED_UNICODE);

        return "{{ t({$safeText}) }}";
    }

    protected function injectUseLang(string $scriptContent, bool $isSetup): string
    {
        $hasImport = (bool) preg_match('/import\s+.*?useLang.*?from/i', $scriptContent);
        $hasDestructure = (bool) preg_match('/const\s+\{\s*t\s*\}\s*=\s*useLang/i', $scriptContent);

        if ($hasImport && $hasDestructure) {
            return $scriptContent;
        }

        $scriptContent = str_replace("\r\n", "\n", $scriptContent);
        $lines = explode("\n", $scriptContent);
        $lastImportIndex = -1;

        $inImport = false;
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if (preg_match('/^import\b/', $trimmed)) {
                $inImport = true;
                $lastImportIndex = $index;
            }
            if ($inImport) {
                $lastImportIndex = $index;
                if (preg_match('/from\s*[\'"][^\'"]+[\'"];?$/', $trimmed) || str_ends_with($trimmed, ';')) {
                    $inImport = false;
                }
            }
        }

        if (! $hasImport) {
            $importLine = "import { useLang } from '@/composables/use-lang';";
            if ($lastImportIndex !== -1) {
                array_splice($lines, $lastImportIndex + 1, 0, [$importLine]);
                $lastImportIndex++;
            } else {
                $inserted = false;
                foreach ($lines as $index => $line) {
                    if (trim($line) !== '') {
                        array_splice($lines, $index, 0, [$importLine]);
                        $lastImportIndex = $index;
                        $inserted = true;
                        break;
                    }
                }
                if (! $inserted) {
                    $lines[] = $importLine;
                }
            }
        }

        if (! $hasDestructure && $isSetup) {
            $destructureLine = 'const { t } = useLang();';
            if ($lastImportIndex !== -1) {
                array_splice($lines, $lastImportIndex + 1, 0, ['', $destructureLine]);
            } else {
                array_unshift($lines, $destructureLine, '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return string[]
     */
    protected function getIgnorePatterns(): array
    {
        $ignoreFile = base_path('.translateignore');
        if (! File::exists($ignoreFile)) {
            return [];
        }

        $lines = file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $patterns = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $patterns[] = $this->globToRegex($line);
        }

        return $patterns;
    }

    protected function globToRegex(string $glob): string
    {
        $glob = trim($glob, '/');
        $escaped = preg_quote($glob, '#');
        $escaped = str_replace('\*\*\/', '.*', $escaped);
        $escaped = str_replace('\*\*$', '.*', $escaped);
        $escaped = str_replace('\*\*', '.*', $escaped);
        $escaped = str_replace('\*', '[^/]*', $escaped);
        $escaped = str_replace('\?', '[^/]', $escaped);

        return '#(?:^|/)'.$escaped.'(?:$|/)#i';
    }
}
