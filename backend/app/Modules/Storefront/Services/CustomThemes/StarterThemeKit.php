<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * The starter theme package (VEN-036a): every file of resources/theme-starter zipped into
 * a temporary file for download (theme.json at the ZIP root). It passes the validator
 * as-is (CustomThemeValidationTest) and is the reference in docs/themes/README.md.
 */
class StarterThemeKit
{
    public const FILENAME = 'nogrgr-starter-theme.zip';

    public function directory(): string
    {
        return resource_path('theme-starter');
    }

    /**
     * @return array<string, string> package path => bytes
     */
    public function contents(): array
    {
        $root = $this->directory();
        $contents = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $contents[$path] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($contents);

        return $contents;
    }

    /**
     * Build the ZIP in a temp file and return its path (the caller deletes it after sending).
     */
    public function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nt-starter-');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the starter theme ZIP.');
        }

        foreach ($this->contents() as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }

        $zip->close();

        return $path;
    }
}
