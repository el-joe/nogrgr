<?php

namespace App\Modules\Platform\Concerns;

use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Add to any model whose create/update/delete must appear in the audit log.
 * Action names: "<snake_model>.created|updated|deleted". Hidden attributes are skipped.
 *
 * @phpstan-require-extends Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(static function (Model $model): void {
            self::writeAudit($model, AuditAction::Created, ['after' => self::auditableValues($model, $model->getAttributes())]);
        });

        static::updated(static function (Model $model): void {
            $changed = self::auditableValues($model, $model->getChanges());
            unset($changed[$model->getUpdatedAtColumn() ?? 'updated_at']);

            if ($changed === []) {
                return;
            }

            $before = array_intersect_key(self::auditableValues($model, $model->getOriginal()), $changed);

            self::writeAudit($model, AuditAction::Updated, ['before' => $before, 'after' => $changed]);
        });

        static::deleted(static function (Model $model): void {
            self::writeAudit($model, AuditAction::Deleted, ['before' => self::auditableValues($model, $model->getOriginal())]);
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function writeAudit(Model $model, AuditAction $action, array $changes): void
    {
        app(Auditor::class)->record(Str::snake(class_basename($model)).'.'.$action->value, $model, $changes);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function auditableValues(Model $model, array $values): array
    {
        return array_diff_key($values, array_flip($model->getHidden()));
    }
}
