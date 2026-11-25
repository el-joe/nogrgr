<?php

use App\Modules\Ai\Contracts\AiTranslatable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Symfony\Component\Finder\Finder;

/*
| Decision #44 / #52: every Catalog and Inventory model with translatable columns
| implements Ai\Contracts\AiTranslatable and lists exactly those columns.
*/

it('marks every translatable catalog and inventory model as AiTranslatable', function (): void {
    $offenders = [];

    foreach (['Catalog', 'Inventory'] as $module) {
        foreach (Finder::create()->files()->name('*.php')->in(app_path("Modules/{$module}/Models")) as $file) {
            $class = "App\\Modules\\{$module}\\Models\\".$file->getBasename('.php');

            if (! is_subclass_of($class, Model::class) || ! in_array(HasTranslations::class, class_uses_recursive($class), true)) {
                continue;
            }

            $model = new $class;

            if (! $model instanceof AiTranslatable || $model->aiTranslatableFields() !== $model->getTranslatableAttributes()) {
                $offenders[] = $class;
            }
        }
    }

    expect($offenders)->toBe([]);
});
