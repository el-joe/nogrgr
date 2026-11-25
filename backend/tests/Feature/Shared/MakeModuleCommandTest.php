<?php

use App\Shared\Modules\Console\MakeModuleCommand;
use Illuminate\Support\Facades\File;

it('scaffolds a module with the standard layout and provider', function (): void {
    $name = 'Zz'.Str::studly(Str::random(8));
    $path = app_path('Modules/'.$name);

    try {
        $this->artisan('make:module', ['name' => $name, '--no-register' => true])->assertSuccessful();

        foreach (MakeModuleCommand::DIRECTORIES as $directory) {
            expect(is_dir($path.'/'.$directory))->toBeTrue();
        }

        expect(File::get($path.'/ModuleServiceProvider.php'))
            ->toContain("namespace App\\Modules\\{$name};")
            ->toContain('extends BaseModuleServiceProvider');
    } finally {
        File::deleteDirectory($path);
    }
});

it('rejects invalid module names', function (): void {
    $this->artisan('make:module', ['name' => '1nvalid', '--no-register' => true])->assertFailed();
});
