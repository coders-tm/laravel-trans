<?php

namespace Nitro\Trans\Tests\Helpers;

class FileFactory
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function blade(string $relativePath, string $content): string
    {
        return $this->create($relativePath, $content);
    }

    public function vue(string $relativePath, string $content): string
    {
        return $this->create($relativePath, $content);
    }

    public function js(string $relativePath, string $content): string
    {
        return $this->create($relativePath, $content);
    }

    public function ts(string $relativePath, string $content): string
    {
        return $this->create($relativePath, $content);
    }

    public function php(string $relativePath, string $content): string
    {
        return $this->create($relativePath, $content);
    }

    protected function create(string $relativePath, string $content): string
    {
        $fullPath = $this->basePath.'/'.$relativePath;
        $dir = dirname($fullPath);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);

        return $fullPath;
    }
}
