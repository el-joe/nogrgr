<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageReport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Files of custom theme versions on the private `custom-themes` disk:
 * `{tenant}/{version}/…` = the extracted (validated) package, `compiled/{tenant}/{version}`
 * = Twig's compiled-template cache. Only paths from the version's validated file list are
 * ever read back, so nothing outside the package can be reached.
 */
class CustomThemePackageStore
{
    public const DISK = 'custom-themes';

    /**
     * @var array<int, array<string, string>>
     */
    private array $memo = [];

    /**
     * @param  array<string, string>  $contents
     */
    public function put(CustomThemeVersion $version, array $contents): void
    {
        foreach ($contents as $path => $bytes) {
            $this->disk()->put($version->directory().'/'.$path, $bytes);
        }
    }

    /**
     * Every file of a version (path => bytes) — templates, locales, theme.json, assets.
     *
     * @return array<string, string>
     */
    public function contents(CustomThemeVersion $version, bool $withAssets = false): array
    {
        $key = $version->id * 2 + ($withAssets ? 1 : 0);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $contents = [];

        foreach (array_keys($version->files()) as $path) {
            if (! $withAssets && str_starts_with($path, 'assets/')) {
                $contents[$path] = '';

                continue;
            }

            $bytes = $this->disk()->get($version->directory().'/'.$path);

            if (is_string($bytes)) {
                $contents[$path] = $bytes;
            }
        }

        return $this->memo[$key] = $contents;
    }

    /**
     * @return array<string, string>
     */
    public function templates(CustomThemeVersion $version): array
    {
        return ThemePackageReport::templatesOf($this->contents($version));
    }

    /**
     * Absolute path of one validated package file, else null.
     */
    public function path(CustomThemeVersion $version, string $path): ?string
    {
        if (! array_key_exists($path, $version->files()) || ! $this->disk()->exists($version->directory().'/'.$path)) {
            return null;
        }

        return $this->disk()->path($version->directory().'/'.$path);
    }

    public function compiledPath(CustomThemeVersion $version): string
    {
        return $this->disk()->path('compiled/'.$version->tenant_id.'/'.$version->id);
    }

    public function clearCompiled(CustomThemeVersion $version): void
    {
        $this->disk()->deleteDirectory('compiled/'.$version->tenant_id.'/'.$version->id);
        unset($this->memo[$version->id * 2], $this->memo[$version->id * 2 + 1]);
    }

    public function delete(CustomThemeVersion $version): void
    {
        $this->clearCompiled($version);
        $this->disk()->deleteDirectory($version->directory());
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
