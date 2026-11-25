<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\PlanFeature;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Http\Requests\Admin\PlanRequest;
use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * Plan form input. Prices arrive as decimals and are stored as USD minor units;
 * limits map PlanLimit values to int|null (empty = unlimited).
 */
final class PlanData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string|null>  $description
     * @param  array<string, string|null>  $highlights
     * @param  array<string, int|null>  $limits
     * @param  list<string>  $features
     */
    public function __construct(
        public readonly array $name,
        public readonly array $description,
        public readonly array $highlights,
        public readonly ?string $icon,
        public readonly PlanStatus $status,
        public readonly int $monthlyPriceMinor,
        public readonly int $yearlyPriceMinor,
        public readonly int $trialDays,
        public readonly int $sortOrder,
        public readonly array $limits,
        public readonly array $features,
    ) {}

    public static function fromPlanRequest(PlanRequest $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $limits = [];

        foreach (PlanLimit::cases() as $limit) {
            $value = $data['limits'][$limit->value] ?? null;
            $limits[$limit->value] = $value === null || $value === '' ? null : (int) $value;
        }

        $features = array_values(array_filter(
            PlanFeature::values(),
            fn (string|int $feature): bool => (bool) ($data['features'][$feature] ?? false),
        ));

        return new self(
            name: array_filter((array) $data['name'], fn ($value): bool => $value !== null && $value !== ''),
            description: array_filter((array) ($data['description'] ?? []), fn ($value): bool => $value !== null && $value !== ''),
            highlights: array_filter((array) ($data['highlights'] ?? []), fn ($value): bool => $value !== null && $value !== ''),
            icon: $data['icon'] ?? null,
            status: PlanStatus::from((string) $data['status']),
            monthlyPriceMinor: Money::of((string) $data['monthly_price'])->minor,
            yearlyPriceMinor: Money::of((string) $data['yearly_price'])->minor,
            trialDays: (int) ($data['trial_days'] ?? 0),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            limits: $limits,
            features: array_map('strval', $features),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'highlights' => $this->highlights,
            'icon' => $this->icon,
            'status' => $this->status,
            'monthly_price_minor' => $this->monthlyPriceMinor,
            'yearly_price_minor' => $this->yearlyPriceMinor,
            'currency' => Money::BASE_CURRENCY,
            'trial_days' => $this->trialDays,
            'sort_order' => $this->sortOrder,
            'limits' => $this->limits,
            'features' => $this->features,
        ];
    }
}
