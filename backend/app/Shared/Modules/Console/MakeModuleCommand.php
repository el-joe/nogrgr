<?php

namespace App\Shared\Modules\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * Folder layout of a module (docs/plan/02-architecture.md).
     */
    public const DIRECTORIES = [
        'Actions',
        'DTOs',
        'DataTables',
        'Database/Factories',
        'Database/Seeders',
        'Database/migrations/central',
        'Database/migrations/tenant',
        'Enums',
        'Events',
        'Http/Controllers/Admin',
        'Http/Controllers/Affiliate',
        'Http/Controllers/Api',
        'Http/Controllers/Central',
        'Http/Controllers/Storefront',
        'Http/Controllers/Vendor',
        'Http/Middleware',
        'Http/Requests',
        'Http/Resources',
        'Jobs',
        'Listeners',
        'Models',
        'Notifications',
        'Policies',
        'Repositories/Contracts',
        'Repositories/Eloquent',
        'Services',
        'resources/lang/ar',
        'resources/lang/en',
        'resources/views',
        'routes',
    ];

    public const CONFIG_MARKER = '// make:module inserts above this line';

    protected $signature = 'make:module {name : StudlyCase module name, e.g. Catalog} {--no-register : Do not add the module to config/modules.php}';

    protected $description = 'Scaffold a new module in app/Modules with the standard folder layout and provider';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly((string) $this->argument('name'));

        if ($name === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->components->error('Invalid module name.');

            return self::FAILURE;
        }

        $base = app_path('Modules/'.$name);
        $provider = $base.'/ModuleServiceProvider.php';

        if ($files->exists($provider)) {
            $this->components->warn("Module [{$name}] already exists; adding any missing folders.");
        }

        foreach (self::DIRECTORIES as $directory) {
            $path = $base.'/'.$directory;
            $files->ensureDirectoryExists($path);

            if ($files->isEmptyDirectory($path)) {
                $files->put($path.'/.gitkeep', '');
            }
        }

        if (! $files->exists($provider)) {
            $stub = (string) $files->get(__DIR__.'/../stubs/module-provider.stub');
            $files->put($provider, str_replace('{{ module }}', $name, $stub));
        }

        if (! $this->option('no-register')) {
            $this->register($files, $name);
        }

        $this->components->info("Module [{$name}] ready at app/Modules/{$name}.");

        return self::SUCCESS;
    }

    private function register(Filesystem $files, string $name): void
    {
        $config = config_path('modules.php');
        $line = "App\\Modules\\{$name}\\ModuleServiceProvider::class,";
        $contents = (string) $files->get($config);

        if (str_contains($contents, $line)) {
            return;
        }

        if (! str_contains($contents, self::CONFIG_MARKER)) {
            $this->components->warn('Marker not found in config/modules.php; register the provider manually.');

            return;
        }

        $files->put($config, str_replace(
            '        '.self::CONFIG_MARKER,
            '        '.$line.PHP_EOL.'        '.self::CONFIG_MARKER,
            $contents,
        ));
    }
}
