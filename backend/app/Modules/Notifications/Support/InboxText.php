<?php

namespace App\Modules\Notifications\Support;

/**
 * Renders the title / body of a stored inbox row (`data` of a database notification):
 * `<field>_key` translated with `<field>_params` (panel rows) or the shared `params`
 * (customer rows), or a literal `<field>` text.
 *
 * Param keys are normalised to translation placeholders (RQA-APP-014, decision #128):
 * customer order rows used to store the mail-template variables with dotted names
 * (`order.number`) while the texts use `:order_number`, so every reader maps `.` to `_`
 * — existing rows render correctly without a data migration.
 */
final class InboxText
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(array $data, string $field, ?string $locale = null): string
    {
        $key = $data[$field.'_key'] ?? null;

        if (! is_string($key) || $key === '') {
            return trim((string) (is_scalar($data[$field] ?? null) ? $data[$field] : ''));
        }

        $params = is_array($data[$field.'_params'] ?? null) ? $data[$field.'_params'] : ($data['params'] ?? []);
        $text = __($key, self::params(is_array($params) ? $params : []), $locale);

        return is_string($text) && $text !== $key ? $text : '';
    }

    /**
     * Translation replacements from stored params: scalar values only, `.` in names
     * becomes `_` (`order.number` => `order_number`).
     *
     * @param  array<array-key, mixed>  $params
     * @return array<string, string>
     */
    public static function params(array $params): array
    {
        $replace = [];

        foreach ($params as $name => $value) {
            if (! is_string($name) || ! (is_scalar($value) || $value === null)) {
                continue;
            }

            $replace[str_replace('.', '_', $name)] = (string) $value;
        }

        return $replace;
    }
}
