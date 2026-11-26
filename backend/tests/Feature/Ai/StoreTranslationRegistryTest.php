<?php

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Support\StoreTranslationRegistry;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/*
| Decision #44: every store-owned (tenant DB) model with translatable columns implements
| AiTranslatable and is covered by the paid full-store translation registry, and every
| section has exactly one source.
*/

it('covers every tenant model with translatable columns', function (): void {
    $covered = app(StoreTranslationRegistry::class)->models();
    $missing = [];

    foreach (glob(app_path('Modules/*/Models/*.php')) as $file) {
        $class = 'App\\Modules\\'.Str::of($file)->after(app_path('Modules/'))->beforeLast('.php')->replace('/', '\\');

        if (! class_exists($class)) {
            continue;
        }

        $traits = class_uses_recursive($class);

        if (! in_array(HasTranslations::class, $traits, true) || in_array(CentralConnection::class, $traits, true)) {
            continue;
        }

        if (! is_subclass_of($class, AiTranslatable::class) || ! in_array($class, $covered, true)) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([]);
});

it('has one source per section', function (): void {
    expect(array_keys(app(StoreTranslationRegistry::class)->sources()))->toBe(StoreTranslationSection::values());
});
