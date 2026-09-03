<?php

namespace Trans\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Trans\LaravelTransServiceProvider;
use Trans\Tests\Helpers\FileFactory;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir().'/laravel-trans-tests/'.uniqid('trans_', true);
        if (! file_exists($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }

        $this->breakSymlinks();

        foreach (['resources/lang', 'resources/views', 'resources/js'] as $dir) {
            $path = base_path($dir);
            if (! file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPath)) {
            $this->deleteDirectory($this->tempPath);
        }

        parent::tearDown();
    }

    protected function factory(): FileFactory
    {
        return new FileFactory($this->tempPath);
    }

    protected function getPackageProviders($app): array
    {
        return [LaravelTransServiceProvider::class];
    }

    protected function breakSymlinks(): void
    {
        $symlinkedDirs = ['resources/lang', 'resources/views', 'resources/js'];

        foreach ($symlinkedDirs as $relativePath) {
            $path = base_path($relativePath);

            if (! is_link($path)) {
                continue;
            }

            $target = readlink($path);
            $files = glob($target.'/*') ?: [];

            unlink($path);
            mkdir($path, 0755, true);

            foreach ($files as $file) {
                if (is_file($file)) {
                    copy($file, $path.'/'.basename($file));
                }
            }
        }
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
