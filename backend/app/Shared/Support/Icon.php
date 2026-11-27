<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Inline SVG icons from resources/svg/icons (exported from the Figma Icons frame
 * 24:10147: 24 grid, 1.75 stroke, currentColor). Used by <x-ui::icon>.
 */
final class Icon
{
    /**
     * Directional icons mirrored under [dir=rtl].
     *
     * @var list<string>
     */
    public const DIRECTIONAL = ['chevron-left', 'chevron-right', 'arrow-up', 'arrow-down', 'return', 'log-out', 'panel-left'];

    /**
     * Name prefixes of one-screen art (ad-* Ad Studio) and panel chrome (tp-* tenant panel
     * navigation): not offered in icon pickers (<x-ui::icon-picker>).
     *
     * @var list<string>
     */
    public const INTERNAL_PREFIXES = ['ad-', 'tp-'];

    /**
     * @var array<string, string>
     */
    private static array $cache = [];

    public static function path(string $name): string
    {
        return resource_path('svg/icons/'.$name.'.svg');
    }

    public static function exists(string $name): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $name) === 1 && is_file(self::path($name));
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $files = glob(resource_path('svg/icons/*.svg')) ?: [];

        return array_values(array_map(static fn (string $file): string => basename($file, '.svg'), $files));
    }

    /**
     * @param  string  $attributes  extra, already escaped HTML attributes (class, data-*, …)
     */
    public static function svg(string $name, int $size = 20, string $attributes = '', ?string $label = null): string
    {
        if (! self::exists($name)) {
            throw new InvalidArgumentException("Unknown icon [{$name}].");
        }

        $source = self::$cache[$name] ??= trim((string) file_get_contents(self::path($name)));

        $a11y = $label !== null && $label !== ''
            ? 'role="img" aria-label="'.e($label).'"'
            : 'aria-hidden="true"';

        $flip = in_array($name, self::DIRECTIONAL, true) ? ' data-flip' : '';

        $open = sprintf('<svg width="%1$d" height="%1$d" focusable="false" %2$s%3$s data-icon="%4$s" %5$s', $size, $a11y, $flip, $name, trim($attributes));

        return (string) preg_replace('/^<svg/', rtrim($open), $source, 1);
    }

    /**
     * Icons a user may pick (labels, plans, links…): every icon except the internal ones.
     *
     * @return list<string>
     */
    public static function pickable(): array
    {
        return array_values(array_filter(self::names(), static fn (string $name): bool => ! Str::startsWith($name, self::INTERNAL_PREFIXES)));
    }

    /**
     * Readable, translatable icon title: `ui.icons.<name>` when translated, else the
     * humanised name ("credit-card-2" → "Credit card 2").
     */
    public static function title(string $name): string
    {
        $key = 'ui.icons.'.$name;

        return Lang::has($key) ? (string) __($key) : Str::ucfirst(str_replace('-', ' ', $name));
    }

    /**
     * Options of <x-ui::icon-picker>, sorted by title. A current value that is internal
     * (or picked before it became internal) stays listed so editing keeps it.
     *
     * @return list<array{value: string, label: string, svg: string}>
     */
    public static function pickerOptions(?string $current = null): array
    {
        $names = self::pickable();

        if ($current !== null && $current !== '' && self::exists($current) && ! in_array($current, $names, true)) {
            $names[] = $current;
        }

        $options = array_map(static fn (string $name): array => [
            'value' => $name,
            'label' => self::title($name),
            'svg' => self::svg($name, 18),
        ], $names);

        usort($options, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $options;
    }
}
