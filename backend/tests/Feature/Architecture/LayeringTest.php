<?php

use App\Shared\Http\Controller;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Symfony\Component\Finder\Finder;
use Tests\Support\AppClasses;

/*
| Layering rules (CLAUDE.md): Controller (thin) → FormRequest → DTO → Service → Repository → Model.
*/

arch('no Livewire anywhere in the app')
    ->expect('App')
    ->not->toUse('Livewire');

arch('no debugging helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('the shared kernel does not depend on modules')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

arch('shared repositories implement the contract')
    ->expect('App\Shared\Repositories\BaseRepository')
    ->toImplement(RepositoryContract::class);

foreach (AppClasses::modules() as $module) {
    arch("{$module}: controllers do not build queries")
        ->expect("App\\Modules\\{$module}\\Http\\Controllers")
        ->not->toUse([
            'Illuminate\Support\Facades\DB',
            'Illuminate\Database\Eloquent\Builder',
            'Illuminate\Database\Query\Builder',
            'Illuminate\Database\Connection',
            'Illuminate\Support\Facades\Schema',
        ]);

    arch("{$module}: controllers extend the shared base controller")
        ->expect("App\\Modules\\{$module}\\Http\\Controllers")
        ->classes()
        ->toExtend(Controller::class);

    arch("{$module}: repositories implement the repository contract")
        ->expect("App\\Modules\\{$module}\\Repositories\\Eloquent")
        ->classes()
        ->toImplement(RepositoryContract::class);

    arch("{$module}: repository contracts are interfaces")
        ->expect("App\\Modules\\{$module}\\Repositories\\Contracts")
        ->toBeInterfaces();

    arch("{$module}: enums are enums")
        ->expect("App\\Modules\\{$module}\\Enums")
        ->toBeEnums();
}

it('has no Livewire package or Blade directives', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(array_keys($composer['require'] + $composer['require-dev']))->not->toContain('livewire/livewire', 'livewire/volt', 'livewire/flux');

    $offenders = [];
    $views = Finder::create()->files()->name('*.blade.php')->in([resource_path('views'), app_path('Modules')]);

    foreach ($views as $view) {
        if (preg_match('/(@livewire|<livewire:|\bwire:[a-z]+)/i', $view->getContents()) === 1) {
            $offenders[] = $view->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps Eloquent query calls out of controllers', function (): void {
    $pattern = '/\b[A-Z][A-Za-z0-9_]*::(query|where[A-Za-z]*|find[A-Za-z]*|first[A-Za-z]*|all|create|forceCreate|update[A-Za-z]*|upsert|insert|destroy|with|withCount|latest|oldest|orderBy|select|paginate|count|pluck|firstOrCreate|updateOrCreate)\s*\(/';

    $offenders = [];

    foreach (AppClasses::modules() as $module) {
        foreach (AppClasses::in("Modules/{$module}/Http/Controllers") as $class => $file) {
            if (preg_match_all($pattern, (string) file_get_contents($file), $matches) > 0) {
                $offenders[$class] = array_values(array_unique($matches[0]));
            }
        }
    }

    expect($offenders)->toBe([], 'Controllers must call services/repositories instead of models.');
});

it('only declares backed enums', function (): void {
    $unbacked = [];

    foreach (AppClasses::in('') as $class => $file) {
        if (preg_match('/^\s*enum\s+\w+/m', (string) file_get_contents($file)) !== 1) {
            continue;
        }

        if (! enum_exists($class) || ! (new ReflectionEnum($class))->isBacked()) {
            $unbacked[] = $class;
        }
    }

    expect($unbacked)->toBe([]);
});
