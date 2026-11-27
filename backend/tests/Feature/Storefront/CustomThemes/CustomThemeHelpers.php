<?php

use App\Modules\Storefront\Services\CustomThemes\StarterThemeKit;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageValidator;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeTwigFactory;

if (! function_exists('customThemeZip')) {
    /**
     * A theme ZIP built from the starter package: `$files` adds / replaces entries
     * (null removes one). Written to a unique temp file (parallel-safe).
     *
     * @param  array<string, string|null>  $files
     */
    function customThemeZip(array $files = [], ?callable $tweak = null): string
    {
        $contents = app(StarterThemeKit::class)->contents();

        foreach ($files as $path => $bytes) {
            if ($bytes === null) {
                unset($contents[$path]);
            } else {
                $contents[$path] = $bytes;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'nt-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($contents as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }

        if ($tweak !== null) {
            $tweak($zip);
        }

        $zip->close();

        return $path;
    }

    /**
     * Render inline templates in the theme sandbox (fixture runtime).
     *
     * @param  array<string, string>  $templates
     * @param  array<string, mixed>  $context
     */
    function sandboxRender(array $templates, string $name = 'page.twig', array $context = []): string
    {
        $runtime = ThemePackageValidator::fixtureRuntime(['assets/css/theme.css' => ''], null);
        $twig = ThemeTwigFactory::make($templates, $runtime);

        return ThemeTwigFactory::render($twig, $name, $context);
    }

    function themeManifestJson(array $overrides = []): string
    {
        $manifest = json_decode(app(StarterThemeKit::class)->contents()['theme.json'], true);

        return (string) json_encode(array_replace($manifest, $overrides));
    }
}
