<?php

namespace App\Modules\Identity\Support;

use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Str;

/**
 * Human labels and the before/after diff of audit events (ADM-112 / 112a).
 *  - translated identity::audit.actions.<action> (nested keys), else AuditAction label
 *  - "<model>.created|updated|deleted"   → "Admin updated"
 *  - "http.<route name>"                 → "<Resource>: <verb>" (QA-FINAL-011, never the raw name)
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
            return self::requestLabel(Str::after($action, 'http.'));
        }

        if (preg_match('/^([a-z0-9_]+)\.(created|updated|deleted|restored)$/', $action, $matches) === 1) {
            return (string) __('identity::audit.model_action', [
                'subject' => self::subjectLabel($matches[1]),
                'verb' => __('identity::audit.verbs.'.$matches[2]),
            ]);
        }

        return Str::headline(str_replace('.', ' ', $action));
    }

    /** Panel route-name prefixes dropped from request labels. */
    private const ROUTE_PREFIXES = ['vendor-central.', 'vendor.', 'admin.', 'owner.', 'affiliate.', 'store-api.v1.', 'storefront.'];

    /**
     * Human label of an HTTP fallback event: "admin.catalog.products.store" → "Products:
     * created". The resource is the segment before the controller action (resourceLabel); the verb comes from
     * identity::audit.http_verbs.<action> (store / update / destroy …), else headline.
     */
    public static function requestLabel(string $route): string
    {
        $name = $route;

        foreach (self::ROUTE_PREFIXES as $prefix) {
            if (Str::startsWith($name, $prefix)) {
                $name = Str::after($name, $prefix);

                break;
            }
        }

        $segments = array_values(array_filter(explode('.', $name), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return (string) __('identity::audit.request');
        }

        $verb = count($segments) > 1 ? array_pop($segments) : 'visit';
        $resource = (string) end($segments);

        return (string) __('identity::audit.http_label', [
            'resource' => self::resourceLabel($resource),
            'verb' => self::translatedOr('identity::audit.http_verbs.'.str_replace('-', '_', $verb), Str::lower(Str::headline($verb))),
        ]);
    }

    /**
     * identity::audit.resources.<key>, else the panel menu label (navigation.admin / vendor),
     * else the headline of the segment.
     */
    private static function resourceLabel(string $segment): string
    {
        $key = str_replace('-', '_', $segment);

        foreach (['identity::audit.resources.'.$key, 'navigation.admin.'.$key, 'navigation.vendor.'.$key] as $candidate) {
            $translated = __($candidate);

            if (is_string($translated) && $translated !== $candidate) {
                return $translated;
            }
        }

        return Str::headline($segment);
    }

    private static function translatedOr(string $key, string $fallback): string
    {
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $fallback;
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
