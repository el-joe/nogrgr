<?php

namespace App\Shared\Support;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/**
 * Helpers for the x-ui::* form components: field ids, the dotted error key that
 * matches Laravel's 422 contract (items[0][qty] ↔ items.0.qty), server-side
 * errors and old input that also work outside a session (component tests,
 * mail previews).
 */
final class Ui
{
    public static function errorKey(string $name): string
    {
        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }

    public static function id(string $name, ?string $suffix = null): string
    {
        $base = Str::slug(str_replace(['[', ']', '.'], '-', $name), '-');

        return ($base !== '' ? $base : 'field').($suffix !== null ? '-'.$suffix : '');
    }

    public static function error(string $key, mixed $errors = null): ?string
    {
        $bag = $errors ?? (app('view')->shared('errors') ?? null);

        if ($bag instanceof ViewErrorBag || $bag instanceof MessageBag) {
            $message = $bag->first($key);

            return $message !== '' ? $message : null;
        }

        return null;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        $request = request();

        if (! $request->hasSession()) {
            return $default;
        }

        return $request->old($key, $default);
    }

    /**
     * Translated label of a spatie role name (ui.roles.<name>), falling back to a
     * headline of the slug for roles added later.
     */
    public static function roleLabel(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        $key = 'ui.roles.'.$role;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($role);
    }

    /**
     * Initials for avatar discs ("Sara Admin" → "SA").
     */
    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name)) ?: [];
        $letters = array_map(static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice(array_filter($words), 0, 2));

        return implode('', $letters) ?: '?';
    }

    /**
     * Sparkline points for a 0..$width × 0..$height SVG polyline.
     *
     * @param  list<int|float>  $values
     */
    public static function sparkline(array $values, int $width = 72, int $height = 24): string
    {
        $values = array_values($values);
        $count = count($values);

        if ($count === 0) {
            return '0,'.$height / 2 .' '.$width.','.$height / 2;
        }

        if ($count === 1) {
            $values[] = $values[0];
            $count = 2;
        }

        $min = min($values);
        $max = max($values);
        $span = $max - $min ?: 1;
        $points = [];

        foreach ($values as $index => $value) {
            $x = round($index * $width / ($count - 1), 2);
            $y = $max === $min ? $height / 2 : round($height - 2 - (($value - $min) / $span) * ($height - 4), 2);
            $points[] = $x.','.$y;
        }

        return implode(' ', $points);
    }
}
