<?php

namespace Nitro\Trans\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nitro\Trans\LaravelTransServiceProvider;
use Nitro\Trans\Tests\Helpers\FileFactory;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected string $tempPath;

    protected ?FileFactory $fileFactory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir().'/laravel-trans-tests/'.uniqid('trans_', true);
        if (! file_exists($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }

        // Set scan_dirs to point to our temp directory
        config(['trans.scan_dirs' => [$this->tempPath]]);
        config(['trans.scan_js_dirs' => [$this->tempPath]]);

        $this->fileFactory = new FileFactory($this->tempPath);
    }

    protected function factory(): FileFactory
    {
        return $this->fileFactory;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPath)) {
            $this->deleteDirectory($this->tempPath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [LaravelTransServiceProvider::class];
    }

    protected function createTempFile(string $relativePath, string $content): string
    {
        $fullPath = $this->tempPath.'/'.$relativePath;
        $dir = dirname($fullPath);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    protected function createLangFile(string $locale, array $translations): string
    {
        $langPath = base_path("resources/lang");
        if (! file_exists($langPath)) {
            mkdir($langPath, 0755, true);
        }

        $filePath = $langPath.'/'.$locale.'.json';
        file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $filePath;
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! file_exists($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }

        rmdir($directory);
    }
}
