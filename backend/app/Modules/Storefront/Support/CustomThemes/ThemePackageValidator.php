<?php

namespace App\Modules\Storefront\Support\CustomThemes;

use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeRuntime;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeTwigFactory;
use Throwable;
use Twig\Error\Error as TwigError;
use ZipArchive;

/**
 * Validates an uploaded custom theme ZIP (decision #121) without extracting anything to
 * disk: archive limits (size, file count, ZIP-bomb ratio), safe paths (no absolute paths,
 * `..`, backslashes, hidden files, symlinks, duplicates), an allow-list of file types per
 * folder with magic-byte checks for images / fonts, SVG / CSS / PHP-tag scanning,
 * theme.json schema, required templates and locales, a sandboxed Twig compile + security
 * check of every template and a smoke render of every page with ThemeFixtures.
 */
final class ThemePackageValidator
{
    /**
     * Allowed asset extensions under assets/.
     *
     * @var list<string>
     */
    public const ASSET_EXTENSIONS = ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf'];

    /**
     * Root files allowed besides theme.json (never served).
     *
     * @var list<string>
     */
    public const ROOT_FILES = ['theme.json', 'README.md', 'LICENSE', 'LICENSE.md', 'LICENSE.txt', 'CHANGELOG.md', 'screenshot.png', 'screenshot.jpg', 'screenshot.webp'];

    public function validate(string $zipPath): ThemePackageReport
    {
        $report = new ThemePackageReport;
        $size = @filesize($zipPath);

        if ($size === false || $size === 0) {
            $report->error('The file is empty or could not be read.');

            return $report;
        }

        if ($size > CustomThemeLimits::ZIP_MAX_BYTES) {
            $report->error(sprintf('The ZIP is larger than %d MB.', CustomThemeLimits::ZIP_MAX_BYTES / 1048576));

            return $report;
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            $report->error('The file is not a valid ZIP archive.');

            return $report;
        }

        try {
            $this->readEntries($zip, $report);
        } finally {
            $zip->close();
        }

        if (! $report->passes()) {
            $report->contents = [];

            return $report;
        }

        $this->checkStructure($report);

        if ($report->passes()) {
            $this->compile($report);
        }

        if (! $report->passes()) {
            $report->contents = [];
        }

        return $report;
    }

    private function readEntries(ZipArchive $zip, ThemePackageReport $report): void
    {
        $count = $zip->numFiles;
        $entries = [];
        $total = 0;

        if ($count > CustomThemeLimits::MAX_FILES * 2) {
            $report->error(sprintf('The package has more than %d files.', CustomThemeLimits::MAX_FILES));

            return;
        }

        for ($index = 0; $index < $count; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                $report->error('The ZIP archive is damaged.');

                return;
            }

            $name = (string) $stat['name'];

            if (str_ends_with($name, '/')) {
                continue; // directory entry
            }

            if (str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store' || basename($name) === 'Thumbs.db') {
                continue; // OS metadata, ignored
            }

            $problem = $this->pathProblem($name);

            if ($problem !== null) {
                $report->error($problem);

                continue;
            }

            if ($zip->getExternalAttributesIndex($index, $system, $attributes) && $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                $report->error(sprintf('"%s" is a symbolic link; links are not allowed.', $name));

                continue;
            }

            $unpacked = (int) $stat['size'];
            $packed = max(1, (int) $stat['comp_size']);

            if ($unpacked > 1024 * 64 && $unpacked / $packed > CustomThemeLimits::MAX_COMPRESSION_RATIO) {
                $report->error(sprintf('"%s" is compressed suspiciously well (possible ZIP bomb).', $name));

                continue;
            }

            $total += $unpacked;

            if ($total > CustomThemeLimits::UNPACKED_MAX_BYTES) {
                $report->error(sprintf('The unpacked package is larger than %d MB.', CustomThemeLimits::UNPACKED_MAX_BYTES / 1048576));

                return;
            }

            $entries[$index] = $name;
        }

        if (count($entries) > CustomThemeLimits::MAX_FILES) {
            $report->error(sprintf('The package has more than %d files.', CustomThemeLimits::MAX_FILES));

            return;
        }

        $prefix = $this->commonRoot($entries);
        $seen = [];

        foreach ($entries as $index => $name) {
            $path = $prefix !== '' ? substr($name, strlen($prefix)) : $name;
            $key = strtolower($path);

            if (isset($seen[$key])) {
                $report->error(sprintf('"%s" appears twice in the package.', $path));

                continue;
            }

            $seen[$key] = true;
            $bytes = $zip->getFromIndex($index, CustomThemeLimits::UNPACKED_MAX_BYTES);

            if ($bytes === false) {
                $report->error(sprintf('"%s" could not be read from the ZIP.', $path));

                continue;
            }

            $this->checkFile($path, $bytes, $report);

            $report->files[$path] = ['size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
            $report->contents[$path] = $bytes;
            $report->size += strlen($bytes);
        }

        ksort($report->files);
    }

    /**
     * A ZIP of the theme folder itself ("my-theme/theme.json …") is accepted: the common
     * top folder is stripped when theme.json is not at the root.
     *
     * @param  array<int, string>  $entries
     */
    private function commonRoot(array $entries): string
    {
        if (in_array('theme.json', $entries, true) || $entries === []) {
            return '';
        }

        $first = explode('/', (string) reset($entries))[0].'/';

        foreach ($entries as $name) {
            if (! str_starts_with($name, $first)) {
                return '';
            }
        }

        return in_array($first.'theme.json', $entries, true) ? $first : '';
    }

    private function pathProblem(string $name): ?string
    {
        $shown = mb_substr(preg_replace('/[^\P{C}]/u', '?', $name) ?? '?', 0, 120);

        return match (true) {
            str_contains($name, "\0") => 'A file name contains a null byte.',
            ! mb_check_encoding($name, 'UTF-8') => 'A file name is not valid UTF-8.',
            str_contains($name, '\\') => sprintf('"%s" uses backslashes; use "/" in paths.', $shown),
            str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1 => sprintf('"%s" is an absolute path.', $shown),
            in_array('..', explode('/', $name), true) || in_array('.', explode('/', $name), true) => sprintf('"%s" leaves the package folder (path traversal).', $shown),
            strlen($name) > CustomThemeLimits::MAX_PATH_LENGTH => sprintf('"%s" has a path longer than %d characters.', $shown, CustomThemeLimits::MAX_PATH_LENGTH),
            preg_match('#(^|/)\.#', $name) === 1 => sprintf('"%s" is a hidden file (names starting with "." such as .htaccess are not allowed).', $shown),
            preg_match('#^[A-Za-z0-9._/-]+$#', $name) !== 1 => sprintf('"%s" may only use letters, digits, ".", "_", "-" and "/".', $shown),
            default => null,
        };
    }

    private function checkFile(string $path, string $bytes, ThemePackageReport $report): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $size = strlen($bytes);

        if (preg_match('/\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|exe|bat|htaccess|htpasswd|ini|asp|aspx|jsp)$/i', $path) === 1 || preg_match('/\.php\d?\./i', $path) === 1) {
            $report->error(sprintf('"%s": executable / server files are not allowed.', $path));

            return;
        }

        $text = in_array($extension, ['twig', 'css', 'js', 'json', 'svg', 'md', 'txt', ''], true);

        if ($text && preg_match('/<\?(php|=)/i', $bytes) === 1) {
            $report->error(sprintf('"%s" contains a PHP tag; PHP code is never allowed.', $path));

            return;
        }

        if (in_array($path, self::ROOT_FILES, true)) {
            if ($path === 'theme.json' && $size > CustomThemeLimits::JSON_MAX_BYTES) {
                $report->error(sprintf('theme.json is larger than %d KB.', CustomThemeLimits::JSON_MAX_BYTES / 1024));
            }
            if (str_starts_with($path, 'screenshot.')) {
                $this->checkBinary($path, $extension, $bytes, $report);
            }

            return;
        }

        if (str_starts_with($path, 'templates/')) {
            if ($extension !== 'twig') {
                $report->error(sprintf('"%s": templates/ may only contain .twig files.', $path));
            } elseif ($size > CustomThemeLimits::TEMPLATE_MAX_BYTES) {
                $report->error(sprintf('"%s" is larger than %d KB.', $path, CustomThemeLimits::TEMPLATE_MAX_BYTES / 1024));
            } elseif (! mb_check_encoding($bytes, 'UTF-8')) {
                $report->error(sprintf('"%s" is not UTF-8 text.', $path));
            } else {
                $this->lintTemplate($path, $bytes, $report);
            }

            return;
        }

        if (str_starts_with($path, 'locales/')) {
            if (! in_array($path, ['locales/en.json', 'locales/ar.json'], true)) {
                $report->error(sprintf('"%s": locales/ may only contain en.json and ar.json.', $path));
            } elseif ($size > CustomThemeLimits::JSON_MAX_BYTES) {
                $report->error(sprintf('"%s" is larger than %d KB.', $path, CustomThemeLimits::JSON_MAX_BYTES / 1024));
            } elseif (! self::stringTree(json_decode($bytes, true))) {
                $report->error(sprintf('"%s" must be a JSON object whose values are texts (nested objects allowed).', $path));
            }

            return;
        }

        if (str_starts_with($path, 'assets/')) {
            if (! in_array($extension, self::ASSET_EXTENSIONS, true)) {
                $report->error(sprintf('"%s": .%s files are not allowed in assets/ (allowed: %s).', $path, $extension, implode(', ', self::ASSET_EXTENSIONS)));

                return;
            }

            if ($size > CustomThemeLimits::ASSET_MAX_BYTES) {
                $report->error(sprintf('"%s" is larger than %d MB.', $path, CustomThemeLimits::ASSET_MAX_BYTES / 1048576));

                return;
            }

            match ($extension) {
                'svg' => $this->checkSvg($path, $bytes, $report),
                'css' => $this->checkCss($path, $bytes, $report),
                'js' => mb_check_encoding($bytes, 'UTF-8') ? null : $report->error(sprintf('"%s" is not UTF-8 text.', $path)),
                default => $this->checkBinary($path, $extension, $bytes, $report),
            };

            return;
        }

        $report->error(sprintf('"%s" is not allowed here: use theme.json, templates/, locales/ and assets/.', $path));
    }

    /**
     * The file really is the image / font its extension says (magic bytes).
     */
    private function checkBinary(string $path, string $extension, string $bytes, ThemePackageReport $report): void
    {
        $head = substr($bytes, 0, 16);
        $valid = match ($extension) {
            'png' => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'gif' => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
            'webp' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            'avif' => substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['avif', 'avis'], true),
            'ico' => str_starts_with($head, "\x00\x00\x01\x00"),
            'woff' => str_starts_with($head, 'wOFF'),
            'woff2' => str_starts_with($head, 'wOF2'),
            'ttf' => str_starts_with($head, "\x00\x01\x00\x00") || str_starts_with($head, 'true'),
            'otf' => str_starts_with($head, 'OTTO'),
            default => false,
        };

        if (! $valid) {
            $report->error(sprintf('"%s" is not a real .%s file (content does not match the extension).', $path, $extension));
        }
    }

    private function checkSvg(string $path, string $bytes, ThemePackageReport $report): void
    {
        $patterns = [
            '/<\s*script/i' => 'a <script> element',
            '/\son[a-z]+\s*=/i' => 'an event handler attribute (on…=)',
            '/javascript\s*:/i' => 'a javascript: URL',
            '/<\s*(foreignObject|iframe|embed|object|use)\b/i' => 'an embedded document / <use> element',
            '/<!\s*(ENTITY|DOCTYPE)/i' => 'a DOCTYPE / ENTITY declaration',
            '/(href|src)\s*=\s*["\']\s*(?!#)/i' => 'an external link (href / src)',
            '/@import|url\(\s*["\']?\s*(https?:|\/\/|data:)/i' => 'an external style import',
        ];

        foreach ($patterns as $pattern => $what) {
            if (preg_match($pattern, $bytes) === 1) {
                $report->error(sprintf('"%s": SVG files may not contain %s.', $path, $what));

                return;
            }
        }

        if (! str_contains($bytes, '<svg')) {
            $report->error(sprintf('"%s" is not an SVG image.', $path));
        }
    }

    private function checkCss(string $path, string $bytes, ThemePackageReport $report): void
    {
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $report->error(sprintf('"%s" is not UTF-8 text.', $path));

            return;
        }

        if (preg_match('/@import\s+(url\()?\s*["\']?\s*(https?:|\/\/)|url\(\s*["\']?\s*(https?:|\/\/)/i', $bytes) === 1) {
            $report->error(sprintf('"%s" loads a remote file; put fonts and images in assets/ and use relative url().', $path));
        }

        if (preg_match('/expression\s*\(|javascript\s*:|(?<![-\w])behavior\s*:|-moz-binding/i', $bytes) === 1) {
            $report->error(sprintf('"%s" contains a script expression.', $path));
        }
    }

    private function lintTemplate(string $path, string $source, ThemePackageReport $report): void
    {
        if (preg_match('/<script(?![^>]*type\s*=\s*["\']application\/(ld\+)?json["\'])[^>]*>/i', $source) === 1) {
            $report->warn(sprintf('%s: inline / hard-coded <script> tags are blocked by the store CSP; load theme scripts with {{ script("js/….js") }}.', $path));
        }

        if (preg_match('/<(script|link|img|iframe)[^>]+(src|href)\s*=\s*["\']\s*(https?:)?\/\//i', $source) === 1) {
            $report->warn(sprintf('%s: remote files are blocked by the store CSP; bundle them in assets/.', $path));
        }

        if (preg_match('/\son[a-z]+\s*=\s*["\']/i', $source) === 1) {
            $report->warn(sprintf('%s: inline event handlers (onclick=…) are blocked by the store CSP.', $path));
        }
    }

    private function checkStructure(ThemePackageReport $report): void
    {
        $json = isset($report->contents['theme.json']) ? json_decode($report->contents['theme.json'], true) : null;

        if (! isset($report->contents['theme.json'])) {
            $report->error('theme.json is missing at the root of the package.');
        } elseif (json_last_error() !== JSON_ERROR_NONE) {
            $report->error('theme.json is not valid JSON: '.json_last_error_msg().'.');
        } else {
            $manifest = ThemeManifest::validate($json);

            foreach ($manifest['errors'] as $error) {
                $report->error($error);
            }

            $report->manifest = $manifest['manifest'];

            if (($report->manifest['rtl'] ?? true) === false) {
                $report->warn('theme.json: "rtl" is false; Arabic shoppers will see a left-to-right layout.');
            }
        }

        foreach (CustomThemeTemplate::cases() as $template) {
            if (! isset($report->contents[$template->file()])) {
                $report->error(sprintf('Required template %s is missing.', $template->file()));
            }
        }

        foreach (['locales/en.json', 'locales/ar.json'] as $locale) {
            if (! isset($report->contents[$locale])) {
                $report->error(sprintf('Required strings file %s is missing.', $locale));
            }
        }

        $templateBytes = array_sum(array_map('strlen', $report->templates()));

        if ($templateBytes > CustomThemeLimits::TEMPLATES_MAX_BYTES) {
            $report->error(sprintf('All templates together are larger than %d KB.', CustomThemeLimits::TEMPLATES_MAX_BYTES / 1024));
        }
    }

    /**
     * Compile + security-check every template in the sandbox, then render every page with
     * the fixtures (runtime errors, unknown assets / strings become report entries).
     */
    private function compile(ThemePackageReport $report): void
    {
        $runtime = self::fixtureRuntime($report->contents, $report->manifest);
        $twig = ThemeTwigFactory::make($report->templates(), $runtime);

        foreach (array_keys($report->templates()) as $name) {
            try {
                $twig->load($name)->unwrap()->ensureSecurityChecked();
            } catch (TwigError $error) {
                $report->error(self::describe($error, 'templates/'.$name));
            } catch (Throwable $error) {
                $report->error(sprintf('templates/%s: %s', $name, $error->getMessage()));
            }
        }

        if (! $report->passes()) {
            return;
        }

        foreach (CustomThemeTemplate::pages() as $template) {
            try {
                $html = ThemeTwigFactory::render($twig, $template->value.'.twig', ThemeFixtures::context($template, ['name' => $report->manifest['name'] ?? '', 'version' => $report->manifest['version'] ?? ''], $runtime->settings));

                if (! str_contains($html, 'data-nt-head') && $template !== CustomThemeTemplate::Layout) {
                    $report->warn(sprintf('%s: the page does not print {{ head() }} (SEO tags, security policy and store data are missing).', $template->file()));
                }
            } catch (TwigError $error) {
                $report->error(self::describe($error, $template->file()));
            } catch (Throwable $error) {
                $report->error(sprintf('%s: %s', $template->file(), $error->getMessage()));
            }
        }

        foreach ($runtime->warnings as $warning) {
            $report->warn($warning);
        }
    }

    /**
     * The runtime of a validation / docs render: fixture URLs and money, the package's
     * own assets and strings, schema-default settings.
     *
     * @param  array<string, string>  $contents
     * @param  array<string, mixed>|null  $manifest
     */
    public static function fixtureRuntime(array $contents, ?array $manifest, string $locale = 'en'): ThemeRuntime
    {
        $assets = [];
        $strings = [];

        foreach (array_keys($contents) as $path) {
            if (str_starts_with($path, 'assets/')) {
                $assets[substr($path, strlen('assets/'))] = true;
            }
        }

        foreach (['en', 'ar'] as $code) {
            $decoded = isset($contents['locales/'.$code.'.json']) ? json_decode($contents['locales/'.$code.'.json'], true) : null;
            $strings[$code] = is_array($decoded) ? $decoded : [];
        }

        $settings = ThemeManifest::templateValues($manifest, ThemeManifest::settings($manifest, []));

        return new ThemeRuntime(
            assets: $assets,
            assetBase: '/theme-assets/preview',
            strings: $strings,
            locale: $locale,
            nonce: 'validation',
            settings: $settings,
            csrfToken: 'validation-token',
            url: static fn (string $name, array $params): ?string => ThemeUrls::make($name, $params),
            money: static fn (int $minor): string => '$'.number_format($minor / 100, 2),
            head: static fn (): string => '<meta name="nogrgr-theme" data-nt-head>',
            scripts: static fn (): string => '',
            staticText: static fn (string $key, array $params): string => __st($key, $params),
        );
    }

    public static function describe(TwigError $error, string $file): string
    {
        $line = $error->getTemplateLine();
        $source = $error->getSourceContext()?->getName();
        $file = $source !== null && $source !== '' ? 'templates/'.$source : $file;

        return sprintf('%s%s: %s', $file, $line > 0 ? ' line '.$line : '', $error->getRawMessage());
    }

    private static function stringTree(mixed $value, int $depth = 0): bool
    {
        if (! is_array($value) || $depth > 4 || ($depth === 0 && array_is_list($value) && $value !== [])) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) && ! self::stringTree($item, $depth + 1)) {
                return false;
            }
        }

        return true;
    }
}
