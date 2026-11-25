<?php

namespace Tests\Support;

use Symfony\Component\Finder\Finder;

/**
 * Discovers application classes from the filesystem (PSR-4: App\ => app/).
 */
final class AppClasses
{
    /**
     * @return array<string, string> FQCN => file path
     */
    public static function in(string $relativeDirectory): array
    {
        $root = self::appPath();
        $directory = $root.'/'.trim($relativeDirectory, '/');

        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];

        foreach (Finder::create()->files()->name('*.php')->in($directory) as $file) {
            $relative = substr($file->getRealPath(), strlen($root) + 1, -4);
            $classes['App\\'.str_replace('/', '\\', $relative)] = $file->getRealPath();
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return array_values(array_map('basename', glob(self::appPath().'/Modules/*', GLOB_ONLYDIR) ?: []));
    }

    /**
     * Usable before the application is booted (arch() calls run at file load).
     */
    public static function appPath(): string
    {
        return dirname(__DIR__, 2).'/app';
    }
}
