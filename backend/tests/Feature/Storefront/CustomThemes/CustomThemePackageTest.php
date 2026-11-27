<?php

use App\Modules\Storefront\Support\CustomThemes\CustomThemeLimits;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageReport;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageValidator;

require_once __DIR__.'/CustomThemeHelpers.php';

/*
| Custom theme package validation (VEN-036 upload, decision #121): the starter package
| passes without warnings; unsafe or incomplete packages are rejected with a clear report.
*/

function validateZip(string $path): ThemePackageReport
{
    try {
        return app(ThemePackageValidator::class)->validate($path);
    } finally {
        @unlink($path);
    }
}

it('accepts the starter theme package without errors or warnings', function (): void {
    $report = validateZip(customThemeZip());

    expect($report->errors)->toBe([])
        ->and($report->warnings)->toBe([])
        ->and($report->manifest['name'])->toBe('Starter')
        ->and($report->files)->toHaveKey('templates/layout.twig')
        ->and($report->contents)->toHaveKey('assets/css/theme.css');
});

it('accepts a package zipped with its folder', function (): void {
    $path = customThemeZip(tweak: function (ZipArchive $zip): void {
        for ($index = $zip->numFiles - 1; $index >= 0; $index--) {
            $name = (string) $zip->getNameIndex($index);
            $zip->renameIndex($index, 'my-theme/'.$name);
        }
    });

    expect(validateZip($path)->errors)->toBe([]);
});

it('rejects packages with server code, traversal, hidden files or unknown types', function (array $files, string $message): void {
    $report = validateZip(customThemeZip($files));

    expect($report->passes())->toBeFalse()
        ->and(implode("\n", $report->errors))->toContain($message)
        ->and($report->contents)->toBe([]);
})->with([
    'php file' => [['assets/shell.php' => '<?php system($_GET["c"]);'], 'executable / server files are not allowed'],
    'phtml' => [['templates/x.phtml' => 'x'], 'executable / server files are not allowed'],
    'double extension' => [['assets/img/a.php.png' => "\x89PNG\r\n\x1A\nxxxx"], 'executable / server files are not allowed'],
    'php tag in template' => [['templates/partials/evil.twig' => '<?php echo 1; ?>'], 'contains a PHP tag'],
    'htaccess' => [['assets/.htaccess' => 'AddType application/x-httpd-php .png'], 'hidden file'],
    'path traversal' => [['../../evil.twig' => 'x'], 'path traversal'],
    'absolute path' => [['/etc/cron.d/x' => 'x'], 'absolute path'],
    'unknown folder' => [['server/config.yml' => 'x'], 'is not allowed here'],
    'fake image' => [['assets/img/logo.png' => 'GIF89a not a png'], 'is not a real .png file'],
    'svg script' => [['assets/img/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'], 'SVG files may not contain a <script> element'],
    'svg handler' => [['assets/img/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'], 'event handler attribute'],
    'svg external' => [['assets/img/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.test/x.png"/></svg>'], 'external link'],
    'remote css' => [['assets/css/x.css' => '@import url("https://evil.test/x.css");'], 'loads a remote file'],
    'missing template' => [['templates/product.twig' => null], 'Required template templates/product.twig is missing'],
    'missing locale' => [['locales/ar.json' => null], 'Required strings file locales/ar.json is missing'],
    'bad locale' => [['locales/en.json' => '["a"]'], 'must be a JSON object'],
    'broken manifest' => [['theme.json' => '{"name": '], 'theme.json is not valid JSON'],
    'bad engine' => [fn (): array => ['theme.json' => themeManifestJson(['engine' => 'blade'])], '"engine" must be "nogrgr-twig/1"'],
    'bad setting' => [fn (): array => ['theme.json' => themeManifestJson(['settings' => [['id' => 'Accent!', 'type' => 'color', 'label' => ['en' => 'A', 'ar' => 'ب'], 'default' => 'red']]])], '"id" must be lower_snake_case'],
    'sandbox violation' => [['templates/partials/evil.twig' => '{% autoescape false %}x{% endautoescape %}'], 'Tag "autoescape" is not allowed'],
    'syntax error' => [['templates/home.twig' => '{% extends "layout.twig" %}{% block content %}{{ }{% endblock %}'], 'templates/home.twig line 1'],
    'unknown function' => [['templates/partials/evil.twig' => '{{ exec("id") }}'], 'Unknown "exec" function'],
]);

it('rejects symbolic links, oversized files and ZIP bombs', function (): void {
    $link = customThemeZip(['assets/img/link.png' => '/etc/passwd'], function (ZipArchive $zip): void {
        $zip->setExternalAttributesName('assets/img/link.png', ZipArchive::OPSYS_UNIX, (0120777 << 16));
    });
    expect(implode("\n", validateZip($link)->errors))->toContain('symbolic link');

    $big = customThemeZip(['assets/js/big.js' => str_repeat('a', CustomThemeLimits::ASSET_MAX_BYTES + 10)]);
    expect(implode("\n", validateZip($big)->errors))->toContain('possible ZIP bomb');

    $large = customThemeZip(['assets/js/large.js' => bin2hex(random_bytes(intdiv(CustomThemeLimits::ASSET_MAX_BYTES, 2) + 10))]);
    expect(implode("\n", validateZip($large)->errors))->toContain('larger than 2 MB');

    $notZip = tempnam(sys_get_temp_dir(), 'nt-test-');
    file_put_contents($notZip, 'plain text');
    expect(validateZip($notZip)->errors)->toBe(['The file is not a valid ZIP archive.']);
});

it('reports runtime problems of the smoke render as warnings or errors', function (): void {
    $warn = validateZip(customThemeZip(['templates/partials/extra.twig' => '{{ asset("css/missing.css") }}', 'templates/404.twig' => '{% extends "layout.twig" %}{% block content %}{% include "partials/extra.twig" %}<script>x()</script>{% endblock %}']));
    expect($warn->errors)->toBe([])
        ->and(implode("\n", $warn->warnings))->toContain('asset(): "assets/css/missing.css" is not in the package')
        ->toContain('inline / hard-coded <script> tags are blocked');

    $error = validateZip(customThemeZip(['templates/cart.twig' => '{% extends "layout.twig" %}{% block content %}{{ include("nope.twig") }}{% endblock %}']));
    expect(implode("\n", $error->errors))->toContain('templates/cart.twig');
});
