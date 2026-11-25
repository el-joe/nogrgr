<?php

use App\Modules\Platform\Enums\TenantStatus;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;

final class SampleTenantData extends BaseDTO
{
    public function __construct(
        public readonly string $storeName,
        public readonly TenantStatus $status,
        public readonly ?string $customDomain = null,
        public readonly int $trialDays = 14,
    ) {}
}

it('maps snake_case arrays and casts enums', function (): void {
    $dto = SampleTenantData::fromArray(['store_name' => 'Acme', 'status' => 'active']);

    expect($dto->storeName)->toBe('Acme')
        ->and($dto->status)->toBe(TenantStatus::Active)
        ->and($dto->customDomain)->toBeNull()
        ->and($dto->trialDays)->toBe(14);
});

it('serialises back to snake_case', function (): void {
    expect(SampleTenantData::fromArray(['storeName' => 'Acme', 'status' => TenantStatus::Trial])->toArray())
        ->toBe(['store_name' => 'Acme', 'status' => 'trial', 'custom_domain' => null, 'trial_days' => 14]);
});

it('builds from a form request using validated data only', function (): void {
    $request = new class extends FormRequest
    {
        public function validated($key = null, $default = null): array
        {
            return ['store_name' => 'Validated', 'status' => 'pending'];
        }
    };

    expect(SampleTenantData::fromRequest($request)->storeName)->toBe('Validated');
});

it('copies with changes', function (): void {
    $dto = SampleTenantData::fromArray(['store_name' => 'Acme', 'status' => 'active'])->with(['trial_days' => 30]);

    expect($dto->trialDays)->toBe(30)->and($dto->status)->toBe(TenantStatus::Active);
});

it('fails on missing required fields', function (): void {
    SampleTenantData::fromArray(['status' => 'active']);
})->throws(InvalidArgumentException::class);
