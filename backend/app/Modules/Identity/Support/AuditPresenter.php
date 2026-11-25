<?php

namespace App\Modules\Identity\Support;

use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Str;

/**
 * Human labels and the before/after diff of audit events (ADM-112 / 112a).
 *  - translated identity::audit.actions.<action> (nested keys), else AuditAction label
 *  - "<model>.created|updated|deleted"   → "Admin updated"
 *  - "http.<route name>"                 → "Request: <route name>"
 *  - anything else                       → translated key or headline
 */
final class AuditPresenter
{
    public static function actionLabel(string $action): string
    {
        $key = 'identity::audit.actions.'.$action;
        $translated = __($key);

        if (is_string($translated) && $translated !== $key) {
            return $translated;
        }

        $known = AuditAction::tryFrom($action);

        if ($known !== null) {
            return $known->label();
        }

        if (Str::startsWith($action, 'http.')) {
            return (string) __('identity::audit.http', ['route' => Str::after($action, 'http.')]);
        }

        if (preg_match('/^([a-z0-9_]+)\.(created|updated|deleted|restored)$/', $action, $matches) === 1) {
            return (string) __('identity::audit.model_action', [
                'subject' => self::subjectLabel($matches[1]),
                'verb' => __('identity::audit.verbs.'.$matches[2]),
            ]);
        }

        return Str::headline(str_replace('.', ' ', $action));
    }

    public static function subjectLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '—';
        }

        $base = Str::snake(class_basename($type));
        $key = 'identity::audit.subjects.'.$base;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : Str::headline($base);
    }

    /**
     * Rows of the diff: field, before, after (strings, null = absent).
     *
     * @return list<array{field: string, before: ?string, after: ?string}>
     */
    public static function diff(AuditEvent $event): array
    {
        $changes = $event->changes ?? [];
        $before = is_array($changes['before'] ?? null) ? $changes['before'] : null;
        $after = is_array($changes['after'] ?? null) ? $changes['after'] : null;

        if ($before === null && $after === null) {
            return [];
        }

        $rows = [];

        foreach (array_unique([...array_keys($before ?? []), ...array_keys($after ?? [])]) as $field) {
            $rows[] = [
                'field' => (string) $field,
                'before' => $before !== null && array_key_exists($field, $before) ? self::stringify($before[$field]) : null,
                'after' => $after !== null && array_key_exists($field, $after) ? self::stringify($after[$field]) : null,
            ];
        }

        return $rows;
    }

    /**
     * Context entries that are not a before/after diff (http method, status, input keys…).
     *
     * @return array<string, string>
     */
    public static function context(AuditEvent $event): array
    {
        $context = [];

        foreach ($event->changes ?? [] as $key => $value) {
            if (in_array($key, ['before', 'after'], true)) {
                continue;
            }

            $context[(string) $key] = self::stringify($value);
        }

        return $context;
    }

    public static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
