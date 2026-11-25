<?php

namespace App\Shared\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Helpers for backed enums.
 *
 * Labels are translated from:
 *  - "<module-key>::enums.<snake_enum>.<value>" for enums in app/Modules/<Module>/Enums
 *  - "enums.<snake_enum>.<value>" for everything else (lang/{en,ar}/enums.php)
 * and fall back to the headline of the case name.
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasEnumHelpers
{
    public function label(): string
    {
        $key = static::translationKey().'.'.$this->value;
        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : Str::headline($this->name);
    }

    /**
     * Value => label pairs, ready for Select2 / <select>.
     *
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<int|string>
     */
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    public static function translationKey(): string
    {
        $enum = Str::snake(class_basename(static::class));

        if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', static::class, $matches) === 1) {
            return Str::kebab($matches[1]).'::enums.'.$enum;
        }

        return 'enums.'.$enum;
    }
}
