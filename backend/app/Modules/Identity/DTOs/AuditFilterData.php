<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Audit log filters (list, export): free text, admin actor, tenant, event, period.
 */
final class AuditFilterData extends BaseDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $actor = null,
        public readonly ?int $tenant = null,
        public readonly ?string $event = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  raw (already sanitized) filter values
     */
    public static function fromFilters(array $filters): self
    {
        $string = static fn (string $key): ?string => is_scalar($filters[$key] ?? null) && trim((string) $filters[$key]) !== '' ? trim((string) $filters[$key]) : null;
        $integer = static fn (string $key): ?int => is_numeric($filters[$key] ?? null) && (int) $filters[$key] > 0 ? (int) $filters[$key] : null;
        $date = static fn (string $key): ?string => ($value = $string($key)) !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;

        return new self(
            search: $string('search'),
            actor: $integer('actor'),
            tenant: $integer('tenant'),
            event: $string('event'),
            dateFrom: $date('date_from'),
            dateTo: $date('date_to'),
        );
    }
}
