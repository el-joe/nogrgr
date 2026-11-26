<?php

namespace App\Modules\Reporting\DTOs;

use App\Modules\Reporting\Enums\ReportBucket;
use App\Shared\DTOs\BaseDTO;
use Carbon\CarbonImmutable;

/**
 * Period / store / country filters shared by every report tab, the dashboard and the
 * exports. Dates are inclusive `Y-m-d` days; the default window is the last 30 days.
 */
final class ReportFilterData extends BaseDTO
{
    public const DEFAULT_DAYS = 30;

    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $tenantId = null,
        public readonly ?int $countryId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request input (from, to, tenant_id, country_id)
     */
    public static function fromInput(array $input): self
    {
        $date = static fn (mixed $value): ?string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
        $id = static fn (mixed $value): ?int => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        return new self($date($input['from'] ?? null), $date($input['to'] ?? null), $id($input['tenant_id'] ?? null), $id($input['country_id'] ?? null));
    }

    public function end(): CarbonImmutable
    {
        return $this->to !== null ? CarbonImmutable::parse($this->to)->endOfDay() : CarbonImmutable::today()->endOfDay();
    }

    public function start(): CarbonImmutable
    {
        if ($this->from !== null) {
            $start = CarbonImmutable::parse($this->from)->startOfDay();

            return $start->greaterThan($this->end()) ? $this->end()->startOfDay() : $start;
        }

        return $this->end()->startOfDay()->subDays(self::DEFAULT_DAYS - 1);
    }

    public function days(): int
    {
        return (int) $this->start()->diffInDays($this->end()->startOfDay()) + 1;
    }

    /**
     * The window of the same length right before this one (KPI deltas).
     */
    public function previous(): self
    {
        $end = $this->start()->subDay();

        return new self($end->subDays($this->days() - 1)->toDateString(), $end->toDateString(), $this->tenantId, $this->countryId);
    }

    public function bucket(): ReportBucket
    {
        return ReportBucket::forDays($this->days());
    }

    /**
     * Ordered bucket keys covering the window (`Y-m-d` days or `Y-m` months).
     *
     * @return list<string>
     */
    public function bucketKeys(): array
    {
        $keys = [];
        $bucket = $this->bucket();
        $cursor = $bucket === ReportBucket::Month ? $this->start()->startOfMonth() : $this->start();
        $end = $this->end();

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $bucket->keyOf($cursor->toDateString());
            $cursor = $bucket === ReportBucket::Month ? $cursor->addMonth() : $cursor->addDay();
        }

        return $keys;
    }

    /**
     * Query string of these filters (links, data / export URLs).
     *
     * @return array<string, int|string>
     */
    public function query(): array
    {
        return array_filter([
            'from' => $this->start()->toDateString(),
            'to' => $this->end()->toDateString(),
            'tenant_id' => $this->tenantId,
            'country_id' => $this->countryId,
        ], static fn ($value): bool => $value !== null);
    }
}
