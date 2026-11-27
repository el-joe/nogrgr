<?php

namespace App\Modules\Storefront\Console;

use App\Modules\Storefront\Support\CustomThemes\ThemeVariableReference;
use Illuminate\Console\Command;

/**
 * Regenerates the variable reference of docs/themes/README.md (between the markers) from
 * the real presenters (ThemeFixtures → ThemeViewModels / ThemeShell). `--check` fails
 * when the committed docs are out of date (used by CustomThemeDocsTest).
 */
class ThemeDocsCommand extends Command
{
    protected $signature = 'storefront:theme-docs {--check : Only verify that the README is up to date}';

    protected $description = 'Regenerate the custom Twig theme variable reference in docs/themes/README.md';

    public function handle(): int
    {
        $path = self::readme();
        $readme = is_file($path) ? (string) file_get_contents($path) : '';
        $start = strpos($readme, ThemeVariableReference::BEGIN);
        $end = strpos($readme, ThemeVariableReference::END);

        if ($start === false || $end === false || $end < $start) {
            $this->error('docs/themes/README.md has no variable reference markers.');

            return self::FAILURE;
        }

        $updated = substr($readme, 0, $start).ThemeVariableReference::markdown().substr($readme, $end + strlen(ThemeVariableReference::END));

        if ($this->option('check')) {
            if ($updated !== $readme) {
                $this->error('docs/themes/README.md is out of date: run php artisan storefront:theme-docs.');

                return self::FAILURE;
            }

            $this->info('The variable reference is up to date.');

            return self::SUCCESS;
        }

        file_put_contents($path, $updated);
        $this->info('docs/themes/README.md updated.');

        return self::SUCCESS;
    }

    public static function readme(): string
    {
        return dirname(base_path()).'/docs/themes/README.md';
    }
}
